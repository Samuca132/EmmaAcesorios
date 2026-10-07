<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Utilidades comunes para todos los controladores de la API.
 */
abstract class ApiController extends AbstractController
{
    /** @var ValidatorInterface */
    protected $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Decodifica el cuerpo JSON de la petición.
     */
    protected function getJson(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            throw new BadRequestHttpException('El cuerpo de la petición debe ser un JSON válido.');
        }

        return $data;
    }

    /**
     * Valida un array contra un conjunto de restricciones. Devuelve null si es
     * válido o una respuesta 422 con los errores por campo.
     *
     * @param Constraint[] $fields
     */
    protected function validar(array $data, array $fields)
    {
        $violations = $this->validator->validate($data, new Assert\Collection([
            'fields' => $fields,
            'allowExtraFields' => true,
            'missingFieldsMessage' => 'Este campo es obligatorio.',
        ]));

        if (count($violations) === 0) {
            return null;
        }

        $errors = [];
        foreach ($violations as $violation) {
            // "[items][0][cantidad]" => "items.0.cantidad"
            $campo = str_replace('][', '.', trim($violation->getPropertyPath(), '[]'));
            $errors[$campo] = $violation->getMessage();
        }

        return new JsonResponse(['message' => 'Datos inválidos.', 'errors' => $errors], 422);
    }

    protected function noEncontrado($recurso = 'Registro')
    {
        return new NotFoundHttpException($recurso.' no encontrado.');
    }

    protected function error($mensaje, $status = 400)
    {
        return new JsonResponse(['message' => $mensaje], $status);
    }

    /**
     * Restricciones reutilizables.
     */
    protected static function texto($max, $requerido = true)
    {
        $c = [new Assert\Type('string'), new Assert\Length(['max' => $max])];
        if ($requerido) {
            array_unshift($c, new Assert\NotBlank(['message' => 'Este campo es obligatorio.']));
        }

        return $c;
    }

    protected static function numero($requerido = true, $min = 0)
    {
        $c = [
            new Assert\Type(['type' => 'numeric', 'message' => 'Debe ser un número.']),
            new Assert\GreaterThanOrEqual(['value' => $min, 'message' => 'Debe ser mayor o igual a {{ compared_value }}.']),
        ];
        if ($requerido) {
            array_unshift($c, new Assert\NotBlank(['message' => 'Este campo es obligatorio.']));
        }

        return $c;
    }

    protected static function entero($requerido = true, $min = 0)
    {
        $c = [
            new Assert\Type(['type' => 'integer', 'message' => 'Debe ser un número entero.']),
            new Assert\GreaterThanOrEqual(['value' => $min, 'message' => 'Debe ser mayor o igual a {{ compared_value }}.']),
        ];
        if ($requerido) {
            array_unshift($c, new Assert\NotNull(['message' => 'Este campo es obligatorio.']));
        }

        return $c;
    }

    protected static function opcional(array $constraints)
    {
        return new Assert\Optional($constraints);
    }
}
