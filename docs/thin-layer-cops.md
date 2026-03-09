# Thin-Layer Architecture Cops

This document describes the `Architecture/ThinLayer*` cop family.

## Purpose

Thin-layer cops protect entrypoint scripts (controllers/pages/ajax/templates) from accumulating business logic.
The intent is:

- keep orchestration in thin layer,
- keep domain/business behavior in service/lib classes.

## Shared Scope Configuration

`Architecture/ThinLayerBoundary` is the source of truth for file scope:

- `TargetPaths`
- `BusinessLayerPaths`
- `ExcludePaths`

Other thin-layer cops inherit these keys by default.  
If a child cop defines one of these keys explicitly, that value overrides inheritance for that key.

`Enabled` is also inherited from `Architecture/ThinLayerBoundary` unless explicitly set in a child cop section.

## Cop: `Architecture/ThinLayerBoundary`

Anchor cop for shared thin-layer scope.  
Does not report offenses directly.

Default severity: `warning`.

Key config:

```yaml
Architecture/ThinLayerBoundary:
  Enabled: false
  TargetPaths: ["**/*.php"]
  BusinessLayerPaths: []
  ExcludePaths: ["vendor/**"]
```

## Cop: `Architecture/ThinLayerComplexity`

Checks branch-node complexity per file (`if/elseif/for/foreach/while/do/switch/case/ternary`).

Default severity: `warning`.

Key config:

```yaml
Architecture/ThinLayerComplexity:
  MaxBranchNodes: 6
```

## Cop: `Architecture/ThinLayerForbiddenFunctions`

Checks direct function calls in thin layer against `ForbiddenFunctions`.

Default severity: `warning`.

Key config:

```yaml
Architecture/ThinLayerForbiddenFunctions:
  ForbiddenFunctions: ["mysql_query", "mysqli_query", "pg_query"]
```

## Cop: `Architecture/ThinLayerLength`

Checks total file size by line count.

Default severity: `warning`.

Key config:

```yaml
Architecture/ThinLayerLength:
  Max: 25
```

## Cop: `Architecture/ThinLayerSuperglobalUsage`

Checks direct use of configured PHP superglobals in thin-layer scripts.

Default severity: `warning`.

Key config:

```yaml
Architecture/ThinLayerSuperglobalUsage:
  ForbiddenSuperglobals:
    - "_REQUEST"
```

## Cop: `Architecture/ThinLayerForbiddenMethodCalls`

Checks direct object method calls in thin-layer scripts by configured regex patterns.

Default severity: `warning`.

Key config:

```yaml
Architecture/ThinLayerForbiddenMethodCalls:
  ForbiddenMethodPatterns:
    - "^(query|exec|fetch|fetchall|fetchassoc|fetchrow)$"
```

## Cop: `Architecture/ThinLayerGlobalStateUsage`

Checks global state usage in thin-layer scripts:

- `global` keyword (configurable),
- direct usage of configured globals (for example `$GLOBALS`, `$APPLICATION`, `$USER`, `$DB`).

Default severity: `warning`.

Key config:

```yaml
Architecture/ThinLayerGlobalStateUsage:
  CheckGlobalKeyword: true
  ForbiddenGlobals:
    - "GLOBALS"
    - "APPLICATION"
    - "USER"
    - "DB"
```

## Cop: `Architecture/ThinLayerIncludeUsage`

Checks `require/include` usage in thin-layer scripts against `AllowedIncludePatterns`.

Default severity: `warning`.

Key config:

```yaml
Architecture/ThinLayerIncludeUsage:
  AllowedIncludePatterns:
    - "/bitrix/modules/main/include/prolog_before.php"
    - "/bitrix/modules/main/include/prolog_after.php"
    - "/bitrix/header.php"
    - "/bitrix/footer.php"
    - "/local/php_interface/lib/"
    - "/include/"
```


## Cop: `Architecture/ThinLayerForbiddenStaticCalls`

Checks direct `Class::method()` usage in thin layer by:

- `ForbiddenStaticCallPrefixes` (namespace/class prefix match),
- `ForbiddenStaticClasses` (exact class names).

Default severity: `warning`.

Key config:

```yaml
Architecture/ThinLayerForbiddenStaticCalls:
  ForbiddenStaticCallPrefixes:
    - "bitrix\\sale\\"
    - "bitrix\\iblock\\"
  ForbiddenStaticClasses:
    - "csaleorder"
    - "ciblock"
```

## Bitrix Profile

`AllCops.Profile: bitrix` (or `--profile=bitrix`) enables all thin-layer cops and fills thin-layer scope using detected Bitrix root.

## Example Setup

```yaml
Architecture/ThinLayerBoundary:
  Enabled: true
  TargetPaths: ["www_data/**"]
  BusinessLayerPaths:
    - "www_data/local/php_interface/lib/**"
    - "www_data/local/php_interface/migrations/**"
  ExcludePaths: ["vendor/**"]

Architecture/ThinLayerComplexity:
  MaxBranchNodes: 6

Architecture/ThinLayerLength:
  Max: 25

Architecture/ThinLayerSuperglobalUsage:
  ForbiddenSuperglobals:
    - "_REQUEST"

Architecture/ThinLayerForbiddenMethodCalls:
  ForbiddenMethodPatterns:
    - "^(query|exec|fetch|fetchall|fetchassoc|fetchrow)$"

Architecture/ThinLayerGlobalStateUsage:
  CheckGlobalKeyword: true
  ForbiddenGlobals:
    - "GLOBALS"
    - "APPLICATION"
    - "USER"
    - "DB"

Architecture/ThinLayerIncludeUsage:
  AllowedIncludePatterns:
    - "/bitrix/modules/main/include/prolog_before.php"
    - "/bitrix/modules/main/include/prolog_after.php"
    - "/bitrix/header.php"
    - "/bitrix/footer.php"
    - "/local/php_interface/lib/"
    - "/include/"

Architecture/ThinLayerForbiddenFunctions:
  ForbiddenFunctions:
    - "mysql_query"
    - "mysqli_query"
    - "pg_query"

Architecture/ThinLayerForbiddenStaticCalls:
  ForbiddenStaticCallPrefixes:
    - "bitrix\\sale\\"
```
