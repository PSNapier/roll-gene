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
            'genesDict' => ['sometimes', 'required', 'array'],
            'genesDict.*' => ['required', 'array'],
            'genesDict.*.oddsType' => ['required', Rule::in(['punnett', 'percentage'])],
            'genesDict.*.alleles' => ['required', 'array', 'min:1'],
            'genesDict.*.alleles.*' => ['required', 'string', 'max:64'],
            'phenoDict' => ['sometimes', 'array'],
            'phenoDict.*' => ['required', 'array'],
            'phenoDict.*.name' => ['required', 'string', 'max:255'],
            'phenoDict.*.match_mode' => ['sometimes', 'string', 'max:64'],
            'phenoDict.*.phenos' => ['required', 'array'],
            'phenoDict.*.phenos.*' => ['required', 'array'],
            'phenoDict.*.phenos.*.name' => ['required', 'string', 'max:255'],
            'phenoDict.*.phenos.*.alleles' => ['required', 'array', 'min:1'],
            'phenoDict.*.phenos.*.alleles.*' => ['required', 'string', 'max:64'],
            'phenoDict.*.phenos.*.locus_first' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Configure the validator so percentage genes have exactly one allele.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $genesDict = $this->input('genesDict', []);
            if (! is_array($genesDict)) {
                return;
            }
            foreach ($genesDict as $geneName => $entry) {
                $alleles = $entry['alleles'] ?? [];
                if (($entry['oddsType'] ?? '') === 'percentage' && count($alleles) !== 1) {
                    $validator->errors()->add(
                        "genesDict.{$geneName}.alleles",
                        'Percentage genes must have exactly one allele.',
                    );
                }
            }
        });
    }
}
