# Kaisō

Kaiso provides a barebones structure for building controller/view-powered WordPress themes.

It attempts to automatically route a WordPress request to a controller named to match the template you'd like to load. In effect, Kaiso turns the template hierarchy into a router.

You might be interested in the [example project](https://github.com/TomodomoCo/kaiso-example), demonstrating how Kaiso works in context.

## Setup

Kaiso is intended for use within an "app-style" WordPress instance. Our [TomodomoCo/starter-wp](https://github.com/TomodomoCo/starter-wp) framework is a good starting point. It's assumed you're using Composer.

**Step 1**<br>
Add this GitHub repo to your `repositories` block in Composer, add `"tomodomoco/kaiso": "dev-master"` to your require block, and hit `composer update`. The example setup also requires Twig, so install that too (`"twig/twig": "^2.0"` if you're on PHP7+) if you want to use the examples.

**Step 2**<br>
Copy the theme in the `example/theme` folder to your `wp-content/themes` directory (or wherever you place themes in your install).

**Step 3**<br>
Update the settings in the theme's `index.php` file, so the `controllerPath` matches your namespaced path to your controllers. (If you aren't autoloading your controllers, [you should do that](https://getcomposer.org/doc/04-schema.md#psr-4).)

**Step 4**<br>
Copy (and maybe modify) the controllers in `example/controllers/` and the views in `example/views/`; an `app/` folder in your project root is a good spot for them!

**Step 5**<br>
Activate the theme.

**Step 6**<br>
Load your site. You should see "Hello world!"

## FAQ?

**Wait, what?**<br>
That's an excellent question! Kaiso makes it easier to structure a WordPress project with real controllers and views via a modern template engine. It's probably not a solution for self-contained themes: it's best for projects where the theme and the functionality are intrinsically tied (for contexts where your WordPress site starts to look more like a powerful web app).

**I don't understand.**<br>
That's okay! If you are confused, Kaiso might not be right for you. There's nothing wrong with the way things work within WordPress; this just offers another option.

**Is it compatible with Plugin X?**<br>
Probably not. I mean, maybe. Yes?

Kaiso is sort of agnostic to plugins and themes. As far as I can tell, if you still output the right functions in the right places in your template layer, you should still have full compatibility with most plugins. I haven't tested it in detail with any plugins, but you're probably okay if you do everything else right.

**Should I use it in production?**<br>
I wouldn't recommend it. At least not yet, and certainly not if looking at the code makes your head spin.

**Does Kaiso let me add arbitrary routes?**<br>
Not at this point, and possibly never. If that's a critical need for your project, our [starter-slimwp](https://github.com/TomodomoCo/starter-slimwp) approach might be a good option.

## Technical Overview

Kaiso is essentially a router that uses the [WordPress Template Hierarchy](https://developer.wordpress.org/themes/basics/template-hierarchy/) to determine which controller to load.

Instead of a full WordPress theme, we have a single `index.php` file (the final fallback in the hierarchy). That file loads up a small app, which:

1. Creates a [Pimple](https://pimple.symfony.com) container
1. Adds the current `global $wp_query;` to the container
1. Uses [Brain-WP's Hierarchy package](https://github.com/Brain-WP/Hierarchy) to get a list of the templates in the hierarchy for the current `$wp_query`
1. Parses those template names into fully namespaced controller class paths (using the path you provide in the theme's `index.php` file)
1. Checks if each class exists (in order of most-to-least specific) and instantiates the first one it finds, with the Pimple instance passed in
1. Runs (or tries to run) a method from the controller to handle the request, which is one of:
    + `$controller->any()` If this is available in the controller class, it will always be run
    + `$controller->get()`, `->post()`, `->put()`, etc. If `->any()` isn't available, it will load a method corresponding to the value of `$_SERVER['REQUEST']`

In the future, the method names may be customisable and/or mappable. Additionally, it would be cool to create a PSR7-ish Request object that can be passed into the controller methods.

## What doesn't Kaiso do?

Kaiso is designed to be intentionally lightweight, and can be built into most configurations. Here's what's not current possible, or isn't in Kaiso's mission:

+ Kaiso doesn't provide any models. There are several good options for generic WordPress models.
+ Kaiso doesn't let you assign arbitrary routes. (See the FAQ for more.)
+ Kaiso doesn't give you Request and Response objects; you have to `return` your string and set headers with `header()`.
+ Kaiso doesn't make any template engine decisions. Our examples reference Twig, but you're free to use Smarty, Blade, or any other template engine of your choice.
+ Kaiso isn't a framework. It can be part of a framework, but isn't a complete solution in and of itself.
+ Kaiso isn't tested. Not in phpunit, not in real world applications. Try it if you dare!

## About Tomodomo

Tomodomo is a creative agency for communities. We focus on unique design and technical solutions to grow community activity and increase customer retention for online networking forums and customer service communities.

Learn more at [tomodomo.co](https://tomodomo.co) or email us: [hello@tomodomo.co](mailto:hello@tomodomo.co)

## License & Conduct

This project is licensed under the terms of the MIT License, included in `LICENSE.md`.

All open source Tomodomo projects follow a strict code of conduct, included in `CODEOFCONDUCT.md`. We ask that all contributors adhere to the standards and guidelines in that document.

Thank you!
