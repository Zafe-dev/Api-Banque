<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCompteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'sometimes|in:epargne,cheque',
            'solde' => 'sometimes|numeric|min:0',
            'devise' => 'sometimes|in:FCFA,XOF,EUR,USD',
            'statut' => 'sometimes|in:actif,bloque,ferme',
            'titulaire' => 'sometimes|string|max:255',
            'informationsClient' => 'sometimes|array',
            'informationsClient.telephone' => 'sometimes|string|regex:/^\\+221(77|78|76|70|75|33|32)\\d{7}$/',
            'informationsClient.email' => 'sometimes|email',
            'informationsClient.password' => 'sometimes|string|min:10',
            'informationsClient.nci' => 'sometimes|string|nullable',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Le type doit être soit "epargne" soit "cheque"',
            'solde.numeric' => 'Le solde doit être un nombre',
            'solde.min' => 'Le solde ne peut pas être négatif',
            'devise.in' => 'La devise doit être FCFA, XOF, EUR ou USD',
            'statut.in' => 'Le statut doit être actif, bloque ou ferme',
            'titulaire.string' => 'Le titulaire doit être une chaîne de caractères',
            'titulaire.max' => 'Le titulaire ne peut pas dépasser 255 caractères',
            'informationsClient.array' => 'Les informations client doivent être un tableau',
            'informationsClient.telephone.regex' => 'Le numéro de téléphone doit être un numéro sénégalais valide',
            'informationsClient.email.email' => 'L\'adresse email doit être valide',
            'informationsClient.email.unique' => 'Cette adresse email est déjà utilisée',
            'informationsClient.password.min' => 'Le mot de passe doit contenir au moins 10 caractères',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Les données fournies sont invalides',
                'details' => $validator->errors()
            ]
        ], 422));
    }
}
