<?php

namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
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

    /** @var EntityManagerInterface */
    protected $em;

    public function __construct(ValidatorInterface $validator, EntityManagerInterface $em)
    {
        $this->validator = $validator;
        $this->em = $em;
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

        return $this->respuestaDeErrores($violations);
    }

    /**
     * Valida una entidad con las restricciones @Assert de sus propiedades.
     * Devuelve null si es válida o una respuesta 422.
     *
     * @param array $campos traduce propiedades de la entidad a los nombres de la API
     */
    protected function validarEntidad($entidad, array $campos = [])
    {
        return $this->respuestaDeErrores($this->validator->validate($entidad), $campos);
    }

    protected function errorDeCampo($campo, $mensaje)
    {
        return new JsonResponse(['message' => 'Datos inválidos.', 'errors' => [$campo => $mensaje]], 422);
    }

    private function respuestaDeErrores($violations, array $campos = [])
    {
        if (count($violations) === 0) {
            return null;
        }

        $errors = [];
        foreach ($violations as $violation) {
            // "[items][0][cantidad]" => "items.0.cantidad"
            $campo = str_replace('][', '.', trim($violation->getPropertyPath(), '[]'));
            $errors[isset($campos[$campo]) ? $campos[$campo] : $campo] = $violation->getMessage();
        }

        return new JsonResponse(['message' => 'Datos inválidos.', 'errors' => $errors], 422);
    }

    /**
     * Valor de un campo opcional del JSON, recortando espacios en los textos.
     */
    protected static function valor(array $data, $campo, $porDefecto = null)
    {
        if (!array_key_exists($campo, $data) || $data[$campo] === null) {
            return $porDefecto;
        }

        return is_string($data[$campo]) ? trim($data[$campo]) : $data[$campo];
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

    /**
     * Restricción para una lista de renglones (operaciones múltiples).
     *
     * @param array $campos restricciones de cada renglón
     */
    protected static function renglones(array $campos, $max = 100)
    {
        return [
            new Assert\NotBlank(['message' => 'Agregá al menos un renglón.']),
            new Assert\Type('array'),
            new Assert\Count(['min' => 1, 'max' => $max]),
            new Assert\All([
                new Assert\Collection([
                    'fields' => $campos,
                    'missingFieldsMessage' => 'Este campo es obligatorio.',
                ]),
            ]),
        ];
    }

    /**
     * Devuelve los renglones (conservando su índice) ordenados por un campo.
     * Se usa para bloquear filas siempre en el mismo orden y evitar deadlocks.
     */
    protected static function ordenarPor(array $items, $campo)
    {
        uasort($items, function ($a, $b) use ($campo) {
            return $a[$campo] - $b[$campo];
        });

        return $items;
    }
}
