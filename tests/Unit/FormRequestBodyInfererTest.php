<?php

declare(strict_types=1);

use Orchestra\Testbench\TestCase;
use paws1234\LaravelPostmanGenerator\FormRequest\FormRequestBodyInferer;

class DummyFormRequest extends \Illuminate\Foundation\Http\FormRequest {
    public function rules(): array {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
            'age' => 'nullable|integer',
            'tags' => 'array',
            'tags.*' => 'string',
        ];
    }
}

class FormRequestBodyInfererTest extends TestCase
{
    public function test_infer_returns_expected_structure(): void
    {
        $inferer = new FormRequestBodyInferer();
        $result = $inferer->infer(DummyFormRequest::class, true);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('age', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertIsArray($result['tags']);
        $this->assertIsString($result['name']);
        $this->assertIsString($result['email']);
    }

    public function test_infer_returns_null_for_invalid_class(): void
    {
        $inferer = new FormRequestBodyInferer();
        $this->assertNull($inferer->infer('NotAClass'));
    }

    public function test_infer_returns_null_for_non_form_request(): void
    {
        $inferer = new FormRequestBodyInferer();
        $this->assertNull($inferer->infer(\stdClass::class));
    }
}
