# TODO

## Реализовано

- Layout/TrailingWhitespace
- Layout/LineLength
- Lint/DuplicateArrayKey
- Lint/EvalUsage
- Lint/UnreachableCode
- Lint/UnusedVariable
- Style/DoubleQuotes
- Metrics/AbcSize
- Metrics/CyclomaticComplexity
- Metrics/MethodLength
- Metrics/ParameterLists
- Metrics/PerceivedComplexity

## План (следующие cop-ы)

1. Security/Unserialize
- Запрет `unserialize()` без жёсткого whitelist/контроля источника.

2. Security/Exec (или набор для exec/shell_exec/system/passthru/proc_open)
- В веб-проектах это самый частый источник RCE-рисков.

3. Lint/SuppressedError (@...)
- Подавление ошибок прячет реальные баги и усложняет диагностику.

4. Lint/UselessAssignment
- Хорошо ловит "навайбкоженные" хвосты и мёртвые переменные.

5. Lint/ShadowingVariable
- Теневая переменная в длинных PHP-функциях часто даёт тихие логические ошибки.

6. Style/EmptyCatch
- Пустые catch без комментария/логирования лучше запрещать.

7. Style/BooleanLiteralComparison
- Убрать `=== true/false` там, где это шумит и маскирует намерение.

8. Lint/DuplicateMethod (если есть)
- Полезно для раннего обнаружения accidental copy-paste.

## Ранее предложено (добавить в roadmap)

1. Security/EvalAndDynamicInclude
- Расширить текущий `EvalUsage` до `include/require` с динамическими путями.

2. Style/StrictComparison
- Предупреждать `==/!=` и предлагать `===/!==` там, где безопасно.

3. Layout/TrailingCommaInMultiline + --autocorrect
- Быстрая стандартизация стиля и хороший \"wow effect\" от автофикса.
