<?php

namespace AssistedMindfulness\Rake\Tests;

use AssistedMindfulness\Rake\Rake;
use PHPUnit\Framework\TestCase;

class RakeTest extends TestCase
{
    /** @test */
    public function true_is_true()
    {
        $text = "Keyword extraction is not that difficult after all. There are many libraries that can help you with keyword extraction. Rapid automatic keyword extraction is one of those";

        $rake = new Rake();
        $keywords = $rake->extract($text)->sortByScore('desc')->scores();

        $this->assertEquals($keywords, [
            "rapid automatic keyword extraction" => 13.333333333333,
            "keyword extraction"                 => 5.3333333333333,
            "difficult"                          => 1.0,
            "libraries"                          => 1.0,
        ]);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testRussianExtract()
    {
        $text = "Фикус опять болеет. Опадают листья. Осталось 9 листьев. Сегодня вынул из горшка фикус, промыл землю.";

        $rake = new Rake();
        $keywords = $rake->extract($text)->sortByScore('desc')->keywords();

        $this->assertEquals($keywords, [
            0 => "опадают",
            1 => "листья",
            2 => "промыл",
            3 => "землю",
            4 => "горшка",
            5 => "фикус",
            6 => "болеет",
            7 => "осталось",
            8 => "листьев",
            9 => "вынул",
        ]);
    }


    public function testPhrasesExtract()
    {
        $text = "Criteria of compatibility of a system of linear Diophantine equations, " .
            "strict inequations, and nonstrict inequations are considered. Upper bounds " .
            "for components of a minimal set of solutions and algorithms of construction " .
            "of minimal generating sets of solutions for all types of systems are given.";

        $rake = new Rake();
        $phrases = $rake->extract($text)->sortByScore('desc')->keywords();


        $this->assertContains('algorithms', $phrases);
        $this->assertContains('compatibility', $phrases);
        $this->assertContains('components', $phrases);
        $this->assertContains('considered', $phrases);
        $this->assertContains('construction', $phrases);
        $this->assertContains('criteria', $phrases);
        //$this->assertContains('linear diophantine equations', $phrases);
        //$this->assertContains('minimal generating sets', $phrases);
        //$this->assertContains('minimal set', $phrases);
        //$this->assertContains('nonstrict inequations', $phrases);
        $this->assertContains('solutions', $phrases);
        //$this->assertContains('strict inequations', $phrases);
        //$this->assertContains('system', $phrases);
        $this->assertContains('systems', $phrases);
        $this->assertContains('types', $phrases);
        //$this->assertContains('upper bounds', $phrases);
    }


    public function testDonNotFilterNumerics()
    {
        $text = "6462 Little Crest Suite 413 Lake Carlietown, WA 12643";

        $rake = new Rake(0, false);
        $scores = $rake->extract($text)->sortByScore('desc')->scores();

        $this->assertCount(3, $scores);

        $this->assertEquals($scores['12643'], 0);
        $this->assertEquals($scores['6462'], 0);
        $this->assertEquals($scores['crest suite 413 lake carlietown'], 16);
    }
}

