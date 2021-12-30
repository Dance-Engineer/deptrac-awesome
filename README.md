# Deptrac Awesome

Collection of awesome Deptrac extensions.

This collection has only been tested when using Deptrac from source and not from PHAR.

Users beware - The extension points of Deptrac are not stable yet, Deptrac itself did not even have a major release. For that reason code here may break at any moment just by virtue of updating Deptrac. 

This library does not and will not pin down (but may require a minimal) version of Deptrac to prevent any inconsistencies.

Use at your own risk. Should not use for production services but only as a manual "debugging" tool.

##Usage
Just add the following section into your `Deptrac.yaml`:

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
```
