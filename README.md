# Phug Component

Extension for pug-php and phug to use components in templates

## Install

```
composer require phug/component
```

Enable it globally:
```php
\Phug\Component\ComponentExtension::enable();
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
  @alert
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
