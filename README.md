# Santi Hreflang for Magento 2

Módulo Magento 2 para generación automática de etiquetas hreflang entre stores multiidioma.

Compatible con instalaciones Composer en:

```txt
vendor/santi/module-hreflang
```

---

# Características

- Generación automática de etiquetas hreflang
- Compatible con multi-store Magento 2
- Compatible con Hyvä Theme
- Configuración desde backend
- Integrado en:

```txt
Stores > Configuration > Santi Extensions > Hreflang
```

- Eliminación automática de parámetros GET (`?p=2`, etc.)
- Generación opcional de `x-default`
- Instalación vía Composer
- Compatible con estructuras multiidioma por dominio

---

# Instalación

## 1. Añadir repositorio Composer

```bash
composer config repositories.santi-hreflang vcs https://github.com/santimolto/hreflang
```

## 2. Instalar módulo

```bash
composer require santi/module-hreflang:dev-main
```

## 3. Activar módulo

```bash
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Opcional:

```bash
php bin/magento setup:di:compile
```

---

# Actualización

```bash
composer clear-cache
composer update santi/module-hreflang -W

php bin/magento setup:upgrade
php bin/magento cache:flush
```

---

# Desinstalación

```bash
composer remove santi/module-hreflang

php bin/magento setup:upgrade
php bin/magento cache:flush
```

---

# Configuración

Ir a:

```txt
Stores > Configuration > Santi Extensions > Hreflang
```

y activar:

```txt
Enable = Yes
```

---

# Funcionamiento

El módulo detecta automáticamente la URL actual y genera las equivalencias entre stores sustituyendo el dominio base.

Ejemplo:

URL actual:

```txt
https://www.mueblesbonitos.com/salon/mesas
```

Salida generada:

```html
<link rel="alternate" hreflang="es-ES" href="https://www.mueblesbonitos.com/salon/mesas" />
<link rel="alternate" hreflang="fr-FR" href="https://www.designameublement.com/salon/mesas" />
<link rel="alternate" hreflang="pt-PT" href="https://www.moveisbonitos.pt/salon/mesas" />
```

Los parámetros GET se eliminan automáticamente:

```txt
?p=2
?utm_source=
?SID=
```

---

# Mapeo de Stores

El mapeo hreflang se define en:

```txt
Block/Hreflang.php
```

Constante:

```php
private const STORE_HREFLANG_MAP = [
    'designameublement_com_fr' => 'fr-FR',
    'moveisbonitos_pt_pt'      => 'pt-PT',
    'mueblesbonitos_com_es'    => 'es-ES',
];
```

Añadir aquí nuevos stores si se crean más idiomas.

---

# Compatibilidad

Compatible con:

- Magento 2.4.x
- PHP 8.1+
- Hyvä Theme
- Composer installs
- Multi-store Magento

---

# Estructura del módulo

```txt
vendor/santi/module-hreflang/
├── Block/
│   └── Hreflang.php
├── etc/
│   ├── module.xml
│   ├── frontend/
│   │   └── default.xml
│   └── adminhtml/
│       └── system.xml
├── view/
│   └── frontend/
│       └── templates/
│           └── hreflang.phtml
├── composer.json
├── registration.php
└── README.md
```

---

# Notas importantes

## Funcionamiento actual

La generación hreflang funciona mediante sustitución automática de dominios.

Esto funciona correctamente cuando:

- Los productos existen en todos los stores
- Las categorías existen en todos los stores
- Los `url_key` coinciden entre idiomas

---

## Posibles limitaciones

Puede haber problemas si:

- Un producto no existe en otro store
- Cambia el `url_key` entre idiomas
- Existen URL rewrites distintos
- Se usan slugs traducidos

Ejemplo:

```txt
ES:
https://www.mueblesbonitos.com/mesa-roble

FR:
https://www.designameublement.com/table-chene
```

En ese caso el hreflang no coincidiría correctamente.

---

# Mejoras futuras recomendadas

Posibles mejoras avanzadas:

- Resolver URLs por Product ID
- Resolver URLs por Category ID
- Compatibilidad total con URL rewrites
- Generación automática de x-default
- Exclusión de stores sin producto disponible
- Configuración de hreflang desde backend
- Compatibilidad con CMS pages multiidioma

---

# Debug

Comprobar si el módulo está activo:

```bash
php bin/magento module:status Santi_Hreflang
```

Comprobar salida HTML:

```html
<link rel="alternate" hreflang="fr-FR" href="..." />
```

Vaciar caché:

```bash
php bin/magento cache:flush
```

---

# Licencia

OSL-3.0 / AFL-3.0

---

# Autor

Santi Molto

GitHub:

```txt
https://github.com/santimolto
```
