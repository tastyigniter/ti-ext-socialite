This extension for TastyIgniter allows customers to register and log in with their Facebook, Google, Twitter and other social media accounts. 

This extension requires the `Igniter User` extension to add social provider based login to your TastyIgniter website

### Supports
- Facebook
- Twitter
- Google
- **Extensible!** Easily add the one you want!

**Adapters** for other platforms are listed at the community driven (Socialite Providers website)[https://socialiteproviders.github.io/].

### Configuration
You need to enable each social network that you would like to use under Users tab on 
`System > Settings > Socialite settings`. Follow the instructions given below for each social network you would like to use. 

### Usage
- Add `User` and `Socialite` components to your login page
- Copy `/extensions/igniter/user/components/account/login.php` to your themes `_partials/account` folder
- Where you want your login links, add
```
<?php foreach($socialiteLinks as $name => $link) { ?>
    <a href="<?= $link; ?>"><i class="fab fa-2x fa-<?= $name; ?>"></i></a>
<?php } ?>
```

### Components

| Name     | Page variable                | Description                                      |
| -------- | ---------------------------- | ------------------------------------------------ |
| Socialite | `<?= component('socialite') ?>` | Displays the social networks login buttons              |

### Socialite Component

**Properties**

| Property                 | Description              | Example Value | Default Value |
| ------------------------ | ------------------------ | ------------- | ------------- |
| successPage           | Page name to redirect to when the user successfully login/register           | account/account         | account/account        |
| redirectPage          | Page name to redirect to when there is an error       | account/login         |   account/login |

**Variables available in templates**

| Variable                  | Description                                                  |
| ------------------------- | ------------------------------------------------------------ |
| $socialiteLinks | Array of social links                                                |

**Example:**

```
---
title: 'Login'
permalink: /login

'[socialite]':
    errorPage: account/login
    successPage: account/account
---
...
<?= component('socialite') ?>
...
```

