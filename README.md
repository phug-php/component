# Phug Component

Extension for Pug-php and Phug to use components in templates

## Installation

```
composer require phug/component
```

Enable it globally:
```php
\Phug\Component\ComponentExtension::enable();
```

To enable it automatically when calling static methods `render`, `renderFile`,
`display`, `displayFile` etc. on either `\Pug\Facade` or `\Phug\Phug` class.

If using in a `\Pug\Pug` or `\Phug\Renderer` instance, add the `ComponentExtension`
class to modules:
```php
$pug = new \Pug\Pug([/*options*/]);
\Phug\Component\ComponentExtension::enable($pug);
```

## Usage

```pug
//- Register a component
component alert
  .alert.alert-danger
    .alert-title
      slot title
  
    slot

section
  //- Somewhere later in your template
  +alert
    slot title
      | Hello #[em world]!

    p This is an alert!
```

Output:

```html
<section>
  <div class="alert alert-danger">
    <div class="alert-title">
      Hello <em>world</em>!
    </div>

    <p>This is an alert!</p>
  </div>
</section>
```

### Default slots

```pug
component page
  header
    slot header
      | Default header

  slot

  footer
    slot footer
      | Default footer

+page
  | My page content

  slot footer
    | Custom footer
```

Output:

```html
<header>
  Default header
</header>

My page content

<footer>
  Custom footer
</footer>
```

### Parameters

Component inherit mixin behavior.

Parameters can be passed as in mixins:

```pug
component page($title)
  header
    h1=$title

  slot

  footer
    slot footer
      | Footer of #{$title} page

+page("Contact")
  | Contact us
```

(`$title` becomes `title` if you use pug-php or js-phpize)

Output:

```html
<header>
  <h1>
    Contact
  </h1>
</header>

Contact us

<footer>
  Footer of Contact page
</footer>
```

### Fallback component

This package also include a function to get the first defined mixin/component
among given names:

```pug
component page
  | Page component

+#{$firstComponent('customPage', 'page')}
```

Output:

```html
Page component
```

And if `customPage` component is defined, it will be used instead:
```pug
component page
  | Page component

component customPage
  | CustomPage component

+#{$firstComponent('customPage', 'page')}
```

Output:

```html
CustomPage component
```

(`$firstComponent` becomes `firstComponent` if you use pug-php or js-phpize)
`$firstMixin` is also available as an alias.
