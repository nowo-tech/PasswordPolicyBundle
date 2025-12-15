# Release Instructions

## Crear Tag v0.0.1

Para crear el tag de la versión 0.0.1 (primera versión de desarrollo), sigue estos pasos:

### 1. Asegúrate de que todo está commiteado

```bash
git status
git add .
git commit -m "Initial release v0.0.1

- Complete Password Policy Bundle implementation
- Symfony Flex recipe
- Demo projects for Symfony 6.4, 7.0, and 8.0
- 100% test coverage
- Complete documentation
- Twig templates refactoring"
```

### 2. Crear el tag

```bash
git tag -a v0.0.1 -m "Release v0.0.1

Initial development release of Password Policy Bundle

Features:
- Password history tracking
- Password expiry enforcement
- Configurable password policies per entity
- Doctrine lifecycle events integration
- Symfony Flex recipe
- Complete demo projects
- 100% test coverage
- Full documentation

See CHANGELOG.md for complete list of changes."
```

### 3. Push del código y tags

```bash
git push origin master
git push origin v0.0.1
```

### Nota sobre versiones de los demos

Los demos están configurados para usar `^1.0` del bundle. Si usas v0.0.1, necesitarás actualizar los `composer.json` de los demos para usar `^0.0.1` o `dev-master` temporalmente hasta que se publique v1.0.0.

