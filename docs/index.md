---
title: "Socialite Extension"
section: "extensions"
sortOrder: 100
---

## Introduction

This extension for TastyIgniter allows customers to register and log in with their Facebook, Google, Twitter and other
social media accounts.

This extension requires the `Igniter User` extension to add social provider based login to your TastyIgniter website

## Supports

- Facebook
- Twitter
- Google
- **Extensible!** Easily add the one you want!

**Adapters** for other platforms are listed at the community driven (Socialite Providers
website)[https://socialiteproviders.github.io/].

## Installation

To install this extension, click on the **Add to Site** button on the TastyIgniter marketplace item page or search
for **Igniter.Socialite** in **Admin System > Updates > Browse Extensions**

## Configuration

You need to enable each social network that you would like to use under Users tab on
`System > Settings > Socialite settings`. Follow the instructions given below for each social network you would like to
use.

## Usage

- Add `Account` and `Socialite` components to your login page
- Copy `/extensions/igniter/user/components/account/login.php` to your themes `_partials/account` folder
- Copy `/extensions/igniter/socialite/themes/socialite.blade.php` to your themes `_pages` folder
- Where you want your login links, add

```
@foreach($socialiteLinks as $name => $link)
    <a href="{{ $link."?success={$successPage}&error={$errorPage}" }}"><i class="fab fa-2x fa-{{ $name }}"></i></a>
@endforeach
```

## Extend

**Example of Registering Socialite provider**

```
public function registerSocialiteProviders()
{
    return [
        \Igniter\Socialite\SocialiteProviders\Facebook::class => [
            'code' => 'facebook',
            'label' => 'Facebook',
            'description' => 'Log in with Facebook',
        ],
    ];
}
```

**Example of a Socialite Provider Class**

A socialite provider class is responsible for building the settings form, setting the required configuration values,
redirecting and handling callbacks from the provider.

```
class Facebook extends \Igniter\Socialite\Classes\BaseProvider
{
    protected $provider = \Laravel\Socialite\Two\FacebookProvider::class;

    public function extendSettingsForm(Form $form)
    {
        $form->addFields([
            ...
        ], 'primary');
    }

    public function redirectToProvider()
    {
        return Socialite::driver($this->driver)->scopes(['email'])->redirect();
    }

    public function handleProviderCallback()
    {
        return Socialite::driver($this->driver)->user();
    }
}
```
