<?php

namespace Tests\Unit;

use App\Services\PlaceholderRenderer;
use Tests\TestCase;

class PlaceholderRendererTest extends TestCase
{
    private PlaceholderRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new PlaceholderRenderer(maxPasses: 5);
    }

    public function test_simple_placeholder_replacement(): void
    {
        $result = $this->renderer->render([
            'Principal' => 'Juan Dela Cruz',
        ]);

        $this->assertSame('Juan Dela Cruz', $result['Principal']);
    }

    public function test_nested_jurat_replacement(): void
    {
        $result = $this->renderer->render([
            'Date in words' => 'Twelfth day of June 2026',
            'Branch city' => 'Makati City',
            'Tin' => '123-456-789',
            'Jurat bold' => 'SUBSCRIBED AND SWORN',
            'Jurat rest' => 'to before me this [[Date in words]] at [[Branch city]], affiant exhibited to me his/her Taxpayer’s Identification No. [[Tin]].',
        ]);

        $this->assertSame(
            'SUBSCRIBED AND SWORN',
            $result['Jurat bold'],
        );

        $this->assertSame(
            'to before me this Twelfth day of June 2026 at Makati City, affiant exhibited to me his/her Taxpayer’s Identification No. 123-456-789.',
            $result['Jurat rest'],
        );
    }

    public function test_nested_endorsement_replacement(): void
    {
        $result = $this->renderer->render([
            'Endorsement No.' => '2026-001234',
            'Endorsement' => 'W/ENDT.NO. [[Endorsement No.]]',
        ]);

        $this->assertSame('W/ENDT.NO. 2026-001234', $result['Endorsement']);
        $this->assertSame('2026-001234', $result['Endorsement No.']);
    }

    public function test_endorsement_unchecked_resolves_to_blank(): void
    {
        $result = $this->renderer->render([
            'Endorsement No.' => '',
            'Endorsement' => '',
        ]);

        $this->assertSame('', $result['Endorsement']);
        $this->assertSame('', $result['Endorsement No.']);
    }

    public function test_missing_placeholder_does_not_crash(): void
    {
        $result = $this->renderer->render([
            'Clause' => 'Value with [[Missing Placeholder]] inside.',
        ]);

        $this->assertSame('Value with  inside.', $result['Clause']);
    }

    public function test_circular_reference_terminates_safely(): void
    {
        $result = $this->renderer->render([
            'A' => '[[B]]',
            'B' => '[[A]]',
        ]);

        $this->assertSame('', $result['A']);
        $this->assertSame('', $result['B']);
    }

    public function test_maximum_recursion_depth_terminates_safely(): void
    {
        $placeholders = [
            'Level 1' => '[[Level 2]]',
            'Level 2' => '[[Level 3]]',
            'Level 3' => '[[Level 4]]',
            'Level 4' => '[[Level 5]]',
            'Level 5' => '[[Level 6]]',
            'Level 6' => '[[Level 7]]',
        ];

        $result = $this->renderer->render($placeholders);

        $this->assertDoesNotMatchRegularExpression('/\[\[[^\]]+\]\]/', $result['Level 1']);
    }
}
