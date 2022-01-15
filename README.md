# Deptrac Awesome

Collection of awesome Deptrac extensions.

This collection has only been tested when using Deptrac from source and not from PHAR.

Users beware - The extension points of Deptrac are not stable yet, Deptrac itself did not even have a major release. For that reason code here may break at any moment just by virtue of updating Deptrac. 

This library does not and will not pin down (but may require a minimal) version of Deptrac to prevent any inconsistencies.

Use at your own risk. Should not use for production services but only as a manual "debugging" tool.

##Usage
Just add the following section into your `deptrac.yaml`:

```yaml
services:
  - class: DanceEngineer\DeptracAwesome\DependenciesCommand
    autowire: true
    tags:
      - console.command
  - class: DanceEngineer\DeptracAwesome\UnusedDependenciesCommand
    autowire: true
    tags:
      - console.command
  - class: DanceEngineer\DeptracAwesome\GraphVizOutputDisplayFormatter
    autowire: true
    tags:
      - output_formatter
  - class: DanceEngineer\DeptracAwesome\GraphVizOutputDotFormatter
    autowire: true
    tags:
      - output_formatter
  - class: DanceEngineer\DeptracAwesome\GraphVizOutputHtmlFormatter
    autowire: true
    tags:
      - output_formatter
  - class: DanceEngineer\DeptracAwesome\GraphVizOutputImageFormatter
    autowire: true
    tags:
      - output_formatter
```

### DependenciesCommand
Shows you all dependencies of your layer. You can optionally specify a target layer:
```shell
deptrac.php dependencies <layer> <?target layer>
```

### UnusedDependenciesCommand
Show you all the allowed dependencies that are currently not being taken advantage of:
```shell
deptrac.php unused
```

### Graphviz formatters
Improved Graphviz formatters with these additional features:
 - bidirectional edges between nodes are not shown as 2 separate arrows (unless there is a violation), but as on bidirectional blue arrow with the sum of dependencies from both.

Used the same way as regular formatters, but with `graphviz-awesome-` prefix instead of `graphviz-`