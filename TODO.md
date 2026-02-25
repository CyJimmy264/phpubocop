# TODO

## Реализовано

- Layout/TrailingWhitespace
- Layout/LineLength
- Layout/TrailingCommaInMultiline
- Lint/DuplicateArrayKey
- Lint/DuplicateMethod
- Lint/EvalUsage
- Lint/SuppressedError
- Lint/ShadowingVariable
- Lint/UnreachableCode
- Lint/UnusedVariable
- Lint/UselessAssignment
- Style/DoubleQuotes
- Style/EmptyCatch
- Style/BooleanLiteralComparison
- Style/StrictComparison
- Metrics/AbcSize
- Metrics/CyclomaticComplexity
- Metrics/MethodLength
- Metrics/ParameterLists
- Metrics/PerceivedComplexity
- Security/Exec
- Security/EvalAndDynamicInclude
- Security/Unserialize

## План (следующие cop-ы)

## Autocorrect Roadmap

- [x] Layout/TrailingWhitespace
Безопасно удалять хвостовые пробелы/табуляции в конце строк.

- [x] Layout/TrailingCommaInMultiline
Уже реализовано: добавление завершающей запятой в многострочных конструкциях.

- [ ] Style/DoubleQuotes
Менять `\"text\"` на `'text'` только в безопасных случаях без интерполяции/escape-ловушек.

- [ ] Style/StrictComparison
Ограниченный autocorrect `== -> ===`, `!= -> !==` только для безопасных сценариев.

- [ ] Style/BooleanLiteralComparison
Автозамена только там, где нет `T|false`-семантики и не меняется смысл.

## Ранее предложено (добавить в roadmap)
