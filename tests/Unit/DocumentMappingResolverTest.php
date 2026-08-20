<?php

namespace Tests\Unit;

use App\Services\DocumentMappingResolver;
use App\Services\DocumentVariableRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DocumentMappingResolverTest extends TestCase
{
    private DocumentMappingResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DocumentMappingResolver(new DocumentVariableRegistry);
    }

    #[Test]
    public function it_resolves_source_literal_and_composite_mapping(): void
    {
        $variables = ['applicant_name' => 'Yahya', 'letter_number' => '470/01', 'letter_date' => '04 Agustus 2026'];

        $this->assertSame('Nama: Yahya.', $this->resolver->resolve([
            'version' => 1, 'mode' => 'source', 'key' => 'applicant_name', 'prefix' => 'Nama: ', 'suffix' => '.',
        ], 'applicant_name', $variables));
        $this->assertSame('Teks resmi', $this->resolver->resolve([
            'version' => 1, 'mode' => 'literal', 'value' => 'Teks resmi',
        ], 'custom_text', $variables));
        $this->assertSame('Nomor: 470/01', $this->resolver->resolve([
            'version' => 1, 'mode' => 'segments', 'segments' => [
                ['type' => 'literal', 'value' => 'Nomor: '],
                ['type' => 'source', 'key' => 'letter_number'],
            ],
        ], 'letter_number', $variables));
    }

    #[Test]
    public function it_rejects_unknown_variables(): void
    {
        $this->expectException(RuntimeException::class);
        $this->resolver->resolve(null, 'unknown_key', []);
    }
}
