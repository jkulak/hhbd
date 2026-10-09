<?php

use PHPUnit\Framework\TestCase;

/**
 * A review item as the panel reads it (#103).
 */
class Model_Review_ContainerTest extends TestCase
{
    private function item(string $reason, $detail): Model_Review_Container
    {
        return new Model_Review_Container(array(
            'id' => '7', 'entity_type' => 'artist', 'entity_id' => '65', 'reason' => $reason,
            'detail' => $detail, 'created' => '2026-10-09 12:00:00',
        ));
    }

    public function testANamesakeOffersMergeKeepAndQualifierWithItsSuggestions(): void
    {
        $item = $this->item('namesake', '{"text": "same name as hhbd artist 64", "suggestions": [64, "70"]}');

        $this->assertSame('Ta sama nazwa co inny wykonawca', $item->getLabel());
        $this->assertSame(array('merge', 'keep', 'qualifier'), $item->getActions());
        $this->assertSame(array(64, 70), $item->getSuggestions());
        $this->assertSame('same name as hhbd artist 64', $item->getText());
        $this->assertNull($item->resolved);
    }

    public function testADisputedDateOffersTheSourcesValues(): void
    {
        $item = $this->item('date_disputed', '{"values": ["2013", "2013-05-17"]}');

        $this->assertSame(array('pick'), $item->getActions());
        $this->assertSame(array('2013', '2013-05-17'), $item->getValues());
        $this->assertSame('', $item->getText());
    }

    public function testAnItemWithoutDetailHasNoSuggestionsOrValues(): void
    {
        $item = $this->item('single_source', null);

        $this->assertSame(array(), $item->getSuggestions());
        $this->assertSame(array(), $item->getValues());
    }

    public function testAnUnknownReasonIsShownAsItIsAndOffersNothing(): void
    {
        $item = $this->item('something_new', 'not json');

        $this->assertSame('something_new', $item->getLabel());
        $this->assertSame(array(), $item->getActions());
    }
}
