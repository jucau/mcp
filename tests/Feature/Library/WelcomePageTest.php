<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WelcomePageTest extends TestCase
{
    #[Test]
    public function it_presents_the_project_and_leads_to_the_front(): void
    {
        config(['library.front_url' => 'https://front.test']);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Marque-page', 'Model Context Protocol', 'href="https://front.test"', 'Ouvrir ma bibliothèque'], escape: false)
            ->assertDontSee('Laravel v')
            ->assertDontSee('build/');
    }
}
