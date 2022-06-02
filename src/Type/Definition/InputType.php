<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

/**
export type InputType =
  | ScalarType
  | EnumType
  | InputObjectType
  | ListOfType<InputType>
  | NonNull<
      | ScalarType
      | EnumType
      | InputObjectType
      | ListOfType<InputType>,
    >;
 *
 * @phpstan-type InputTypeAlias ScalarType|EnumType|InputObjectType|ListOfType<Type&InputType>|NonNull
 */
interface InputType
{
}
