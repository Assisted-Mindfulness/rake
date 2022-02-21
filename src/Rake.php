<?php

namespace AssistedMindfulness\Rake;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Rake
{
    /** @var string|null */
    private $pattern = null;

    /** @var array */
    private $phrase_scores = [];

    /** @var int */
    private $min_length = 0;

    /** @var bool */
    private $filter_numerics = true;

    /** @var string */
    private $sentence_regex;

    /** @var string */
    private $line_terminator;

    const ORDER_ASC = 'asc';

    const ORDER_DESC = 'desc';

    /**
     * @param $text
     * @param int  $phrase_min_length
     * @param bool $filter_numerics
     */
    public function __construct(int $phrase_min_length = 0, bool $filter_numerics = true)
    {
        $this->min_length = $phrase_min_length;
        $this->filter_numerics = $filter_numerics;

        $this->sentence_regex = '[.!?,;:\t\"\(\)]';
        $this->line_terminator = '\n';
    }

    /**
     * Get loaded stop words and return regex containing each stop word
     */
    private function buildStopwordsRegex()
    {
        $source = file_get_contents(__DIR__ . '/stopwords-all.json');

        $words = json_decode($source, false, 512, JSON_THROW_ON_ERROR);

        return collect($words)
            ->collapse()
            ->values();
    }

    /**
     * Extracts the key phrases from the text.
     *
     * @param $text
     *
     * @throws \JsonException
     *
     * @return $this
     */
    public function extract($text)
    {
        $this->pattern = $this->buildStopwordsRegex();

        $sentences = $this->splitSentences($text);
        $phrases = $this->getPhrases($sentences, $this->pattern);

        $word_scores = $this->calcWordScores($phrases);

        $this->phrase_scores = $this->calcPhraseScores($phrases, $word_scores);

        return $this;
    }

    /**
     * Returns the extracted phrases.
     *
     * @return array
     */
    public function get()
    {
        return array_keys($this->phrase_scores);
    }

    /**
     * Returns the phrases and a score for each of
     * the phrases as an associative array.
     *
     * @return array
     */
    public function scores()
    {
        return $this->phrase_scores;
    }

    /**
     * Returns only the unique keywords within the
     * phrases instead of the full phrases itself.
     *
     * @return array
     */
    public function keywords()
    {
        $keywords = [];
        $phrases = $this->get();

        foreach ($phrases as $phrase) {
            $words = explode(' ', $phrase);
            foreach ($words as $word) {
                // This may look weird to the casual observer
                // but we do this since PHP will convert string
                // array keys that look like integers to actual
                // integers. This may cause problems further
                // down the line when a developer attempts to
                // append arrays to one another and one of them
                // have a mix of integer and string keys.
                if (! $this->filter_numerics || ($this->filter_numerics && ! is_numeric($word))) {
                    if ($this->min_length === 0 || mb_strlen($word) >= $this->min_length) {
                        $keywords[$word] = $word;
                    }
                }
            }
        }

        return array_values($keywords);
    }

    /**
     * Sorts the phrases by score, use 'asc' or 'desc' to specify a
     * sort order.
     *
     * @param string $order Default is 'asc'
     *
     * @return $this
     */
    public function sortByScore($order = self::ORDER_ASC)
    {
        if ($order == self::ORDER_DESC) {
            arsort($this->phrase_scores);
        } else {
            asort($this->phrase_scores);
        }

        return $this;
    }

    /**
     * Sorts the phrases alphabetically, use 'asc' or 'desc' to specify a
     * sort order.
     *
     * @param string $order Default is 'asc'
     *
     * @return $this
     */
    public function sort($order = self::ORDER_ASC)
    {
        if ($order == self::ORDER_DESC) {
            krsort($this->phrase_scores);
        } else {
            ksort($this->phrase_scores);
        }

        return $this;
    }

    /**
     * Splits the text into an array of sentences.
     *
     * @param string $text
     *
     * @return array
     */
    private function splitSentences($text)
    {
        return preg_split('/' . $this->sentence_regex . '/',
            preg_replace('/' . $this->line_terminator . '/', ' ', $text));
    }

    /**
     * Split sentences into phrases by using the stopwords.
     *
     * @param array  $sentences
     * @param string $pattern
     *
     * @return array
     */
    private function getPhrases(array $sentences, $pattern)
    {
        $results = collect();

        foreach ($sentences as $sentence) {
            $phrases = Str::of($sentence)
                ->lower()
                ->explode(' ')
                ->map(function ($word) {
                    return trim($word);
                })
                ->map(function ($word) use ($pattern) {
                    return $pattern->contains($word) ? '|' : $word;
                })
                ->when($this->filter_numerics, function (Collection $collection) {
                    return $collection->filter(function ($word) {
                        return ! is_numeric($word);
                    });
                })
                ->when($this->min_length !== 0, function (Collection $collection) {
                    return $collection->filter(function ($word) {
                        return Str::length($word) >= $this->min_length;
                    });
                })
                ->filter()
                ->implode(' ');

            $results->push(Str::of($phrases)->explode('|')->toArray());
        }

        return $results
            ->flatten()
            ->map(function ($word) {
                return trim($word);
            })
            ->filter()
            ->toArray();
    }

    /**
     * Calculate a score for each word.
     *
     * @param array $phrases
     *
     * @return array
     */
    private function calcWordScores($phrases)
    {
        $frequencies = [];
        $degrees = [];

        foreach ($phrases as $phrase) {
            $words = $this->splitPhraseIntoWords($phrase);
            $words_count = count($words);
            $words_degree = $words_count - 1;

            foreach ($words as $w) {
                $frequencies[$w] = $frequencies[$w] ?? 0;
                $frequencies[$w] += 1;
                $degrees[$w] = $degrees[$w] ?? 0;
                $degrees[$w] += $words_degree;
            }
        }

        foreach ($frequencies as $word => $freq) {
            $degrees[$word] += $freq;
        }

        $scores = [];

        foreach ($frequencies as $word => $freq) {
            $scores[$word] = $scores[$word] ?? 0;
            $scores[$word] = $degrees[$word] / (float) $freq;
        }

        return $scores;
    }

    /**
     * Calculate score for each phrase by word scores.
     *
     * @param array $phrases
     * @param array $scores
     *
     * @return array
     */
    private function calcPhraseScores($phrases, $scores)
    {
        $keywords = [];

        foreach ($phrases as $phrase) {
            $keywords[$phrase] = $keywords[$phrase] ?? 0;
            $words = $this->splitPhraseIntoWords($phrase);
            $score = 0;

            foreach ($words as $word) {
                $score += $scores[$word];
            }

            $keywords[$phrase] = $score;
        }

        return $keywords;
    }

    /**
     * Split a phrase into multiple words and returns them
     * as an array.
     *
     * @param string $phrase
     *
     * @return array
     */
    private function splitPhraseIntoWords($phrase)
    {
        return array_filter(preg_split('/\W+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY), function ($word) {
            return ! is_numeric($word);
        });
    }
}
