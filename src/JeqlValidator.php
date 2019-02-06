<?php

namespace Jeql;

use Jeql\Bags\ArgumentBag;
use Jeql\Bags\DefinitionBag;
use Jeql\Bags\OutputBag;
use Jeql\Bags\RequestBag;
use Jeql\Contracts\Definition;
use Jeql\Contracts\HasInputDefinitions;
use Jeql\Contracts\HasOutputDefinitions;
use Jeql\Contracts\ScalarType;
use Illuminate\Validation\Validator;

class JeqlValidator
{
    /**
     * @param Definition $definition
     * @param Request $request
     */
    public function validate(Definition $definition, Request $request)
    {
        if ($definition instanceof HasInputDefinitions) {
            $this->validateArguments($definition->getInputDefinitions(), $request->getArguments());
        }

        if ($definition instanceof HasOutputDefinitions) {
            $this->validateFields($definition->getOutputDefinitions(), $request->getFields());
        }
    }

    /**
     * @param DefinitionBag $definedInput
     * @param ArgumentBag $givenArguments
     *
     * @throws \Exception
     */
    protected function validateArguments(DefinitionBag $definedInput, ArgumentBag $givenArguments)
    {
        $rules = [];

        // Validate argument syntax
        foreach ($definedInput as $key => $input) {
            $value = $givenArguments->get($key);

            if ($input instanceof InputDefinition) {
                if (!$value instanceof ArgumentBag) {
                    throw new \Exception("Invalid argument for {$key}, expecting array");
                }

                $this->validateArguments($input->getInputDefinitions(), $value);

                continue;
            }

            if ($input instanceof ScalarType) {
                $input->validate($value);

                // Store rules in variable when present
                if (!$rules = $input->getRules()) {
                    $rules[$key] = $rules;
                }

                continue;
            }

            throw new \Exception("Invalid input definition for {$key}");
        }

        // Validate input rules
        $validator = Validator::make($givenArguments->all(), $rules);

        if ($validator->fails()) {
            //throw new ValidationException($validator->getFields());
            throw new \Exception('A validation exception occured');
        }
    }

    /**
     * @param DefinitionBag $definedOuput
     * @param RequestBag $requestFields
     *
     * @throws \Exception
     */
    protected function validateFields(DefinitionBag $definedOuput, RequestBag $requestFields)
    {
        /** @var Request $requestedField */
        foreach ($requestFields as $requestedField) {
            $name = $requestedField->getName();

            /** @var RequestBag $fields */
            if ($fields = $requestedField->getFields()) {
                $subFieldDefinition = $definedOuput->getOutput($name);

                if (!$subFieldDefinition instanceof OutputDefinition) {
                    throw new \Exception("Invalid output definition for {$name}, expecting array");
                }

                $this->validate($subFieldDefinition, $requestedField);
            }

            if (!$definedOuput->has($name)) {
                throw new \Exception("Syntax error: requested field {$name} does not exists");
            }
        }
    }
}