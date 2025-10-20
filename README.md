# Add automations to your filament app
This plugin lets you add automations to your filament app. You can attach triggers and dispatchable actions to your
automations. The plugin will automatically execute the actions when the trigger conditions are met, you can use smart tags in {{variable_name}} in your actions for more compelex actions.

## Table of Contents
- [Installation](#installation)
- [Usage](#usage)
    - [Basics](#basics)
    - [Add the trait to your model](#add-the-trait-to-your-model)
    - [Create an Action](#create-an-action)

## Installation

You can install the package via composer:

```bash
composer require marcoamendola/filament-automations:dev-main
```

You can install the plugin using:

```bash
php artisan filament-automations:install
```

You can publish and run the migrations manually with:

```bash
php artisan vendor:publish --tag="filament-automations-migrations"
php artisan migrate
```

Register the plugin in your `AdminPanelServiceProvider`:

```php
use Automations\FilamentAutomations\FilamentAutomationsPlugin;

->plugins([
    FilamentAutomationsPlugin::make()
])
```

## Usage
### Basics
In order to let your models use automations, you need to add the `InteractsWithAutomations` trait to your model. By adding this trait, the plugin will automatically add a global observer to your model. So when ever a automation matches the event and trigger conditions, the automation will execute the actions.

### Add the trait to your model
```php
use Automations\FilamentAutomations\Concerns\InteractsWithAutomation;

class User extends Model {
  use InteractsWithAutomation;
}
```

### Create an Action
In order to attach an action to your automations, you will have to create a class within the `App\Jobs\Actions` folder. The class must extend the `BaseAction` class. This requires you to implement the `handle` method. This method will be called when the automation is executed.

The action class is very similar to a job.
When ever the action get executed, the model will be passed to the `__construct` method. You can use the model to do whatever you want.

The plugin will find this class on its own. So you don't have to register it anywhere.

```php
<?php

namespace App\Jobs\AutomationActions;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Automations\FilamentAutomations\AutomationActions\BaseAction;

class TestAction extends BaseAction
{
    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(): void
    {
        \Log::info($this->user->name . ' was created at ' . $this->user->created_at);
    }

    public function getActionName(): string
    {
        return 'Azione di prova';
    }

    public function getActionDescription(): string
    {
        return 'Descrizione';
    }

    public function getActionCategory(): string
    {
        return 'Default';
    }

    public function getActionIcon(): string
    {
        return 'heroicon-o-adjustments';
    }
}
```
