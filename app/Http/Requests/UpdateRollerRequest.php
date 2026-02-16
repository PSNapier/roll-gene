<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRollerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $roller = $this->route('roller');

        return $roller && $this->user()?->can('update', $roller);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dictionary' => ['sometimes', 'required', 'array'],
            'dictionary.*' => ['required', 'array'],
            'dictionary.*.oddsType' => ['required', Rule::in(['punnett', 'percentage'])],
            'dictionary.*.alleles' => ['required', 'array', 'min:1'],
            'dictionary.*.alleles.*' => ['required', 'string', 'max:64'],
            'phenos' => ['sometimes', 'array'],
            'phenos.*' => ['required', 'array'],
            'phenos.*.name' => ['required', 'string', 'max:255'],
            'phenos.*.alleles' => ['required', 'array', 'min:1'],
            'phenos.*.alleles.*' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * Configure the validator so percentage genes have exactly one allele.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $dict = $this->input('dictionary', []);
            if (! is_array($dict)) {
                return;
            }
            foreach ($dict as $geneName => $entry) {
                $alleles = $entry['alleles'] ?? [];
                if (($entry['oddsType'] ?? '') === 'percentage' && count($alleles) !== 1) {
                    $validator->errors()->add(
                        "dictionary.{$geneName}.alleles",
                        'Percentage genes must have exactly one allele.',
                    );
                }
            }
        });
    }
}
