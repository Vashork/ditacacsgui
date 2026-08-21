<?php

declare(strict_types=1);

/**
 * Registers the custom validation rule + exception namespaces with the
 * Respect\Validation Factory so that v::theSameNameUsed(...) etc. resolve
 * to tgui\Validation\Rules\* and their failures to tgui\Validation\Exceptions\*Exception.
 *
 * Call this once at bootstrap, BEFORE any validation rule is used.
 */
function initRespectValidationFactory(): void
{
    $factory = (new \Respect\Validation\Factory())
        ->withRuleNamespace('tgui\\Validation\\Rules')
        ->withExceptionNamespace('tgui\\Validation\\Exceptions');

    \Respect\Validation\Factory::setDefaultInstance($factory);
}
