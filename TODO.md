# TODO

## Реализовано

- Layout/TrailingWhitespace
- Layout/LineLength
- Lint/DuplicateArrayKey
- Lint/EvalUsage
- Lint/SuppressedError
- Lint/UnreachableCode
- Lint/UnusedVariable
- Lint/UselessAssignment
- Style/DoubleQuotes
- Metrics/AbcSize
- Metrics/CyclomaticComplexity
- Metrics/MethodLength
- Metrics/ParameterLists
- Metrics/PerceivedComplexity
- Security/Exec
- Security/Unserialize

## План (следующие cop-ы)

- Lint/ShadowingVariable
Теневая переменная в длинных PHP-функциях часто даёт тихие логические ошибки.

- Style/EmptyCatch
Пустые catch без комментария/логирования лучше запрещать.

- Style/BooleanLiteralComparison
Убрать `=== true/false` там, где это шумит и маскирует намерение.

- Lint/DuplicateMethod (если есть)
Полезно для раннего обнаружения accidental copy-paste.

## Ранее предложено (добавить в roadmap)

- Security/EvalAndDynamicInclude
Расширить текущий `EvalUsage` до `include/require` с динамическими путями.

- Style/StrictComparison
Предупреждать `==/!=` и предлагать `===/!==` там, где безопасно.

- Layout/TrailingCommaInMultiline + --autocorrect
Быстрая стандартизация стиля и хороший \"wow effect\" от автофикса.
