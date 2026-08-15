# App Translation Web

The **App Translation Web** package provides a complete web interface for managing, editing, onboarding, and publishing application translations.  
It builds on top of [tobento-ch/app-translation](https://github.com/tobento-ch/app-translation) and integrates seamlessly with [tobento-ch/apps](https://github.com/tobento-ch/apps), enabling you to manage translations across multiple apps from a single, unified UI.

This package is especially useful when:

- you manage translations across multiple apps, modules, or locales  
- you want a browser-based UI for reviewing and editing translations  
- you need import/export support for bulk translation updates  
- you want to detect, log, or auto-translate missing translations  
- you want to onboard new locales efficiently using bulk actions  
- you need a reliable publishing workflow for file-based or in-memory translation resources  
- you want background job support with optional UI monitoring

**Zero-Config Bootstrapping**

The package works out-of-the-box with sensible defaults.

Simply register the [Translation Web Boot](#translation-web-boot) in your application, and the full translation management UI becomes available immediately - no additional configuration required.

You only need to customize configuration if you want to override defaults such as publishing strategies, machine translation providers, permissions, or import/export behavior.  
For [multi-app setups](#manage-translations-across-multiple-apps), make sure all apps use the same translation repository connection (e.g., a shared database or shared storage). Once they share the same repository, the Translation Web UI can manage all apps centrally.

## Table of Contents

- [Getting Started](#getting-started)
    - [Requirements](#requirements)
- [Documentation](#documentation)
    - [App](#app)
    - [Translation Web Boot](#translation-web-boot)
        - [Translation Web Config](#translation-web-config)
    - [Translation Workflow](#translation-workflow)
    - [Features](#features)
        - [Translations Feature](#translations-feature)
            - [Collect Bulk Action](#collect-bulk-action)
            - [Create For Locale Bulk Action](#create-for-locale-bulk-action)
            - [Auto Translate Bulk Action](#auto-translate-bulk-action)
            - [Edit Status Bulk Action](#edit-status-bulk-action)
            - [Delete Bulk Action](#delete-bulk-action)
            - [Export Bulk Action](#export-bulk-action)
            - [Import Bulk Action](#import-bulk-action)
        - [Translations Console Commands Feature](#translations-console-commands-feature)
        - [Machine Translation Feature](#machine-translation-feature)
    - [Collectors](#collectors)
        - [All Translations Collector](#all-translations-collector)
    - [Publishing Strategies](#publishing-strategies)
        - [File Resources Strategy](#file-resources-strategy)
        - [In Memory Resources Strategy](#in-memory-resources-strategy)
        - [Null Strategy](#null-strategy)
    - [Handle Missing Translations](#handle-missing-translations)
        - [Auto Translate Missing Translations](#auto-translate-missing-translations)
        - [Log Missing Translations](#log-missing-translations)
        - [Chain Multiple Handlers](#chain-multiple-handlers)
    - [Onboarding Service](#onboarding-service)
    - [Console](#console)
        - [Collect Translations Command](#collect-translations-command)
        - [Generate JSON Translation Files Command](#generate-json-translation-files-command)
    - [Learn More](#learn-more)
        - [Manage Translations Across Multiple Apps](#manage-translations-across-multiple-apps)
        - [Browser Notification for Background Jobs](#browser-notification-for-background-jobs)
- [Credits](#credits)
___

# Getting Started

Install the latest version of the App Translation Web package by running:

```
composer require tobento/app-translation-web
```

## Requirements

- PHP 8.4 or above

# Documentation

## App

Check out the [**App Skeleton**](https://github.com/tobento-ch/app-skeleton) if you are using the skeleton.

You may also check out the [**App**](https://github.com/tobento-ch/app) to learn more about the app in general.

## Translation Web Boot

The `TranslationWeb` boot performs the following tasks:

* installs and loads translation-web configuration
* implements the translation-web interfaces
* publishes translations using the configured [publishing strategy](#publishing-strategies)
* boots the [Features](#features) defined in the [Translation Web Config](#translation-web-config)

```php
use Tobento\App\AppFactory;
use Tobento\App\Translation\Web\Collector\CollectorsInterface;
use Tobento\App\Translation\Web\Onboarding\OnboardingInterface;
use Tobento\App\Translation\Web\Strategy\TranslationsPublishInterface;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;

// Create the app
$app = new AppFactory()->createApp();

// Add directories:
$app->dirs()
    ->dir(realpath(__DIR__.'/../'), 'root')
    ->dir(realpath(__DIR__.'/../app/'), 'app')
    ->dir($app->dir('app').'config', 'config', group: 'config')
    ->dir($app->dir('root').'public', 'public')
    ->dir($app->dir('root').'vendor', 'vendor');

// Adding boots
$app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
$app->booting();

// Implemented interfaces:
$collectors = $app->get(CollectorsInterface::class);
$onboarding = $app->get(OnboardingInterface::class);
$strategy = $app->get(TranslationsPublishInterface::class);
$translationRepository = $app->get(TranslationRepositoryInterface::class);

// Run the app
$app->run();
```

> [!WARNING]  
> 
> The `TranslationWeb` boot **must be registered before** the [`Translation` boot](https://github.com/tobento-ch/app-translation#translation-boot).  
> 
> This ensures that the translation-web configuration, interfaces, and publishing
> strategy are properly initialized before the core translation system loads.
> 
> If the order is reversed, publishing strategies may not be applied correctly.

You may also install the [App Backend](https://github.com/tobento-ch/app-backend) and [boot the translation web](https://github.com/tobento-ch/app-backend#adding-boots) within the backend application.

### Translation Web Config

The configuration for the translation web is located in the `app/config/translation-web.php` file at the default App Skeleton config location.  
There you can configure the available [features](#features) and other options.

## Translation Workflow

A typical translation workflow using the Translation Web follows these steps:

1. **Developer sets up the application and installs `app-translation-web`**  
   After installation, the developer prepares the translation environment.  
   At this stage, no machine translation is performed.  
   See: [Getting Started](#getting-started), [Translation Web Boot](#translation-web-boot)

2. **Collect translation messages/keys (initial setup)**  
   The developer collects translation keys either:  
   - via the [Collect Translations Command](#collect-translations-command)  
   - through the UI using [Collect Bulk Action](#collect-bulk-action)

   This step scans the application source code and creates or updates translation messages/keys.  
   Machine translation is **not** used during collection.  
   Related: [Collectors](#collectors), [All Translations Collector](#all-translations-collector)

3. **Auto-translate missing or empty translations (optional)**  
   After translation records exist, the developer or translator can use the **Auto Translate** bulk action to fill:  
   - missing translations  
   - empty translations  

   This step uses the configured machine translator and is safe by design:  
   - no overwrites of user-edited values  
   - no messages/key creation  
   - only fills empty or missing entries  

   Users can choose whether to auto-translate:  
   - only selected rows  
   - or all filtered rows  

   See:  
   - [Auto Translate Bulk Action](#auto-translate-bulk-action)  
   - [Machine Translation Feature](#machine-translation-feature)  
   - [Auto Translate Missing Translations](#auto-translate-missing-translations)

4. **Manual editing and refinement (optional)**  
   Translators can manually adjust or override translation values at any time.  
   The UI supports two editing modes:  
   - **inline table editing** for quick adjustments  
   - **full edit view** for detailed editing of a single translation entry  

   Manual edits always take precedence and are never overwritten by automated actions.  
   See: [Translations Feature](#translations-feature)

5. **Export / Import translations (optional)**  
   Users can export translations in any supported format (e.g., CSV, XLSX, JSON) using the **Export** bulk action.  
   This is useful for:  
   - offline translation work  
   - sending untranslated entries to external translators  
   - bulk editing in spreadsheet tools  
   - reviewing translation completeness  

   After editing externally, translations can be re-imported using the **Import** bulk action.  
   Importing updates only the provided entries and respects all safety rules (no message/key creation unless explicitly allowed).  
   See:  
   - [Export Bulk Action](#export-bulk-action)  
   - [Import Bulk Action](#import-bulk-action)

6. **Publish translations**  
   Once translations are ready, the **Edit Status** bulk action can mark the selected entries as *published*.  
   Setting the `status` field to `published` triggers the configured publishing strategy, which handles the actual output, such as:  
   - generating JSON files  
   - updating in-memory resources  
   - or applying any other custom publishing strategy  

   Users can choose which rows to publish using the **selection mode**:  
   - **Selected Rows** - only the manually selected rows will be published  
   - **All Filtered Rows** - all rows matching the current filters will be published  

   Publishing does not modify translation values.  
   It only updates the `status` field and lets the publishing strategy output the translations accordingly.  
   See:  
   - [Edit Status Bulk Action](#edit-status-bulk-action)  
   - [Publishing Strategies](#publishing-strategies)
   
7. **Add a new locale using the Bulk Create action (optional)**  
   When introducing a new locale, the **Bulk Create for Locale** action can be used to initialize translation entries for all existing messages/keys.  
   Users can choose to:  
   - create empty entries for manual translation  
   - automatically pre-fill entries using the machine translator  
   - combine both approaches  

   This step is typically used when expanding the application to support additional languages.  
   See:  
   - [Create For Locale Bulk Action](#create-for-locale-bulk-action)  
   - [app-language config](https://github.com/tobento-ch/app-language#language-config)  
   - [app-language-web](https://github.com/tobento-ch/app-language-web)

This workflow provides a structured and predictable process for managing translations from initial key collection to final publishing.

## Features

### Translations Feature

The **Translations** feature provides a management page for viewing, editing, creating, and publishing translations across all configured apps and locales. It is the central interface for working with translation entries collected by the translation system.

This feature offers:

- an overview **table** of all translations grouped by app, locale, and group  
- **inline table editing** of translation values directly in the list view  
- filtering by app, locale, group, and translation state  
- a **collect** action to gather missing translation messages or keys from source files without overwriting existing translations  
- bulk actions for publishing or deleting translations  
- a **create** action for creating translations for a selected **locale** and **app_id** (e.g. when adding a new locale), optionally using a machine translator  
- **bulk export/import** of translations using the [`app-import-export`](https://github.com/tobento-ch/app-import-export) package  
- support for multi-app environments  

The feature integrates with the configured publishing strategy, ensuring that changes are written to JSON files, in‑memory resources, or any other supported output.

**Config**

In the [translation-web config](#translation-web-config) you can enable and configure this feature:

```php
'features' => [
    new Feature\Translations(
        // A menu name to show the translations link, or null for no menu entry.
        menu: 'main',
        menuLabel: 'Translations',
        // A menu parent name (e.g. 'system') or null if none.
        menuParent: null,
        
        // Optional queue name used for all translation background jobs.
        // If null (default), the default queue is used.
        queueName: 'translations',
        
        // You may disable ACL while testing.
        // Otherwise, only users with the required permissions can access the page.
        withAcl: false,
    ),
],
```

**ACL Permissions**

The Translations feature defines the following ACL permissions:

- `translations` - User can access translations.
- `translations.edit` - User can edit translations.
- `translations.publish` - User can publish translations.
- `translations.delete` - User can delete translations.
- `translations.collect` - User can collect or recollect translations.
- `translations.create` - User can create new translations for a selected locale and app_id.
- `translations.auto-translate` - User can auto-translate translations.

If you are using the [App Backend](https://github.com/tobento-ch/app-backend), you can assign these permissions to roles or users in the backend interface.

#### Collect Bulk Action

The **Collect Bulk Action** scans the application for translation messages (keys) using one or more registered collectors.  
It is used to keep the translation repository in sync with the actual messages found in the codebase.

When triggered, the action:

- runs the selected collectors to detect translation messages  
- creates missing translation entries  
- updates existing entries only when the stored translation is unchanged  
- preserves user-edited translations (never overwritten)  
- dispatches the collection process as a queued background job

Once the background job finishes, the user is notified via a [Browser](https://github.com/tobento-ch/app-notifier#browser-stream-feature) message.  
See [Browser notification for Background jobs](#browser-notification-for-background-jobs) for additional details.

This allows large-scale collection operations to run asynchronously without blocking the UI, while still keeping the user informed when the process completes.

**Queueing**

The job is dispatched to the queue with:

- an optional custom queue name configured via the [Translations Feature](#translations-feature)  
- retry attempts  
- a lowered priority so it does not block more important jobs

You may install [app-job](https://github.com/tobento-ch/app-job) to monitor and manage queued jobs via a web interface.

**Registration**

This bulk action is already registered:

```php
use Tobento\App\Translation\Web\Controller\TranslationCrudController;
use Tobento\App\Translation\Web\Crud\CollectBulkAction;

class TranslationCrudController extends AbstractCrudController
{
    protected function configureActions(): iterable|ActionsInterface
    {
        //...
        
        if ($this->acl->can('translations.collect')) {
            yield new CollectBulkAction(title: trans('Collect'));
        }
        
        //...
    }
}
```

#### Create For Locale Bulk Action

The **Create For Locale Bulk Action** creates missing translation entries for a selected locale and app based on existing translation messages (keys).  
It ensures that every message has a corresponding translation entry for the chosen locale, without overwriting any existing translations.

When triggered, the action:

- validates the user input (locale, app ID, and optional flags)
- creates missing translation entries for the selected locale
- optionally auto-translates missing entries (if enabled and permitted)
- optionally publishes the created entries (if permitted)
- dispatches the creation process as a queued background job
- displays a success message indicating that the process continues in the background

This allows onboarding a new locale or app efficiently without blocking the UI.

Once the background job finishes, the user is notified via a [Browser](https://github.com/tobento-ch/app-notifier#browser-stream-feature) message.  
See [Browser Notification for Background Jobs](#browser-notification-for-background-jobs) for additional details.

**Locales**

Locales displayed in the UI are taken from your application's language configuration.  
If you want to onboard a new locale, add it in the [app-language config](https://github.com/tobento-ch/app-language#language-config)  
or install [**app-language-web**](https://github.com/tobento-ch/app-language-web) to create new locales via the web interface.

**Auto Translate**

If the user has the `translations.auto-translate` permission and auto-translation is supported by the [onboarding service](#onborading-service), the action can automatically translate missing entries using machine translation.  
If the permission is missing, the option is ignored.

**Publish**

If the user has the `translations.publish` permission, the created entries may be marked as published.  
If the permission is missing, the option is ignored.

**Queueing**

The job is dispatched to the queue with:

- an optional custom queue name configured via the [Translations Feature](#translations-feature)  
- retry attempts  
- a lowered priority so it does not block more important jobs

You may install [app-job](https://github.com/tobento-ch/app-job) to monitor and manage queued jobs via a web interface.

**Registration**

This bulk action is already registered:

```php
use Tobento\App\Translation\Web\Controller\TranslationCrudController;
use Tobento\App\Translation\Web\Crud\CreateForLocaleBulkAction;

class TranslationCrudController extends AbstractCrudController
{
    protected function configureActions(): iterable|ActionsInterface
    {
        //...
        
        if ($this->acl->can('translations.create')) {
            yield new CreateForLocaleBulkAction(title: trans('Create for Locale'));
        }
        
        //...
    }
}
```

#### Auto Translate Bulk Action

The **Auto Translate Bulk Action** automatically fills missing or empty translation values for the selected or filtered records using the configured machine translator.  
It never overwrites existing translations and does not create new translation entries.

When triggered, the action:

- validates the user input (selection mode and filters)
- determines which translation records should be processed
- dispatches the auto-translation process as a queued background job
- displays a success message indicating that the process continues in the background

This allows large sets of translations to be auto-translated safely and efficiently without blocking the UI.

Once the background job finishes, the user is notified via a  
[Browser](https://github.com/tobento-ch/app-notifier#browser-stream-feature) message.  
See [Browser Notification for Background Jobs](#browser-notification-for-background-jobs) for additional details.

**Selection Modes**

The action supports two selection modes:

- **Selected Records** - only the explicitly selected rows are auto‑translated  
- **All Filtered Records** - all records matching the current filters are auto‑translated

This makes it possible to translate either a small subset or an entire filtered dataset.

**Auto Translate**

Auto-translation is only performed if:

- the user has the `translations.auto-translate` permission  
- auto-translation is supported by the [Onboarding Service](#onboarding-service)

If either condition is not met, the action is not available in the UI.

Auto-translation:

- fills only missing or empty values  
- never overwrites existing translations  
- does not create new translation entries  
- does not publish translations

**Queueing**

The job is dispatched to the queue with:

- an optional custom queue name configured via the [Translations Feature](#translations-feature)  
- retry attempts  
- a lowered priority so it does not block more important jobs

You may install [app-job](https://github.com/tobento-ch/app-job) to monitor and manage queued jobs via a web interface.

**Registration**

This bulk action is already registered:

```php
use Tobento\App\Translation\Web\Controller\TranslationCrudController;
use Tobento\App\Translation\Web\Crud\AutoTranslateBulkAction;

class TranslationCrudController extends AbstractCrudController
{
    protected function configureActions(): iterable|ActionsInterface
    {
        //...
        
        if ($this->acl->can('translations.auto-translate')) {
            yield new AutoTranslateBulkAction(title: trans('Auto Translate'));
        }
        
        //...
    }
}
```

#### Edit Status Bulk Action

The **Edit Status Bulk Action** allows users to update the `status` field of the selected or filtered translation entries.  
This provides a flexible workflow for marking translations as *draft*, *published*, *pending review*, or *imported*.

The action uses the standard [Bulk Edit Action](https://github.com/tobento-ch/app-crud#bulk-edit-action)  
provided by `app-crud` and does not require a custom implementation.

When triggered, the action:

- validates the user input (selection mode and filters)  
- updates the `status` field for the selected or filtered records  
- applies the changes immediately (no queue is used)  
- displays a success message once the update is complete  

Only users with the `translations.publish` permission can set the status to `published`.  
Users without this permission may edit translations in `draft` or `review` status, but cannot publish entries.

**Registration**

This bulk action is already registered:

```php
use Tobento\App\Crud\Action;
use Tobento\App\Translation\Web\Controller\TranslationCrudController;

class TranslationCrudController extends AbstractCrudController
{
    protected function configureActions(): iterable|ActionsInterface
    {
        //...
        
        if ($this->acl->can('translations.edit')) {
            yield new Action\BulkEdit(name: 'edit-status', title: trans('Edit Status'))
                ->field('status');
        }
        
        //...
    }
}
```

**Available Status Values**

The `status` field supports the following workflow states:

- **Imported** - initial state for imported translations  
- **Draft** - translations that are being edited  
- **Published** - translations ready for runtime use  
- **Pending Review** - translations awaiting approval  

#### Delete Bulk Action

The **Delete Bulk Action** allows users to remove translation entries for the selected or filtered records.  
This action permanently deletes the chosen entries from the translation repository.

When triggered, the action:

- validates the user input (selection mode and filters)  
- determines which translation records should be deleted  
- deletes the selected or filtered entries immediately (no queue is used)  
- displays a success message once the deletion is complete  

This provides a fast and efficient way to clean up unused, obsolete, or incorrectly created translation entries.

**Selection Modes**

The action supports two selection modes:

- **Selected Records** - only the manually selected rows are deleted  
- **All Filtered Records** - all rows matching the current filters are deleted  

This allows users to delete either a small subset or an entire filtered dataset.

**Permissions**

Deletion is only available if the user has the `translations.delete` permission.  
If the permission is missing, the action is not shown in the UI.

**Important Notes**

- Deleting entries is irreversible  
- Deleting a translation entry does **not** delete the underlying message/key  
- Deleting translation entries schedules a publish operation for the affected app.  
  The publishing strategies will remove the deleted entries from output files when the scheduled publish job runs.

**Registration**

The action is registered using the built‑in Delete Bulk Action:

```php
use Tobento\App\Crud\Action;
use Tobento\App\Translation\Web\Controller\TranslationCrudController;

class TranslationCrudController extends AbstractCrudController
{
    protected function configureActions(): iterable|ActionsInterface
    {
        //...
        
        if ($this->acl->can('translations.delete')) {
            yield new Action\BulkDelete(title: trans('Delete'));
        }
        
        //...
    }
}
```

#### Export Bulk Action

The **Export Bulk Action** allows users to export selected or filtered translation records into a downloadable file.  
It is powered by the [`app-import-export`](https://github.com/tobento-ch/app-import-export) package and integrates directly into the Translations CRUD interface.

When triggered, the action:

- determines which translation rows should be exported (selected or filtered)  
- dispatches the export process as a **queued background job**  
- generates an export file using the configured export writer (JSON, NDJSON, CSV, etc.)  
- notifies the user via a browser notification once the export file is ready for download  

The action is registered in the `TranslationCrudController` as follows:

```php
if ($this->acl->can('translations.export')) {
    yield new ExportBulkAction(
        name: 'export',
        title: trans('Export'),
    );
}
```

**Permissions**

The export option is only available when the user has the **`translations.export`** permission.

**Documentation**

For more details, see the official documentation:  
https://github.com/tobento-ch/app-import-export#export-bulk-action

#### Import Bulk Action

The **Import Bulk Action** allows users to import translation records from an external file into the translation repository.  
It is powered by the [`app-import-export`](https://github.com/tobento-ch/app-import-export) package and integrates directly into the Translations CRUD interface.

When triggered, the action:

- validates the uploaded file  
- reads each row using the configured JSON/NDJSON/CSV reader  
- maps incoming fields to the translation model using the defined `withFields`  
- imports or updates translation entries without overwriting existing user-edited translations  
- dispatches the import process as a **queued background job**  
- notifies the user via a browser notification once the import has completed  

The action is registered in the `TranslationCrudController` as follows:

```php
if ($this->acl->can('translations.import')) {
    yield new ImportBulkAction(
        name: 'import',
        title: trans('Import'),
        withFields: [
            'id',
            'app_id',
            'resource_name',
            'resource_locale',
            'resource_group',
            'resource_priority',
            'resource_filename',
            'message',
            'translation',
            'translated_by',
        ],
        withFileFields: [],
    );
}
```

**Permissions**

The import option is only available when the user has the **`translations.import`** permission.

**Documentation**

For more details, see the official documentation:  
https://github.com/tobento-ch/app-import-export#import-bulk-action

### Translations Console Commands Feature

The **Translations Console Commands** feature provides console commands for managing translations from the command line.  
These commands are essential when translation files are modified manually, when new locales or apps are added, or when translations need to be regenerated during deployment or CI workflows.

This feature offers the following commands:

- `translations:collect` - Collect translations from all configured [Collectors](#collectors), or only from selected ones.
- `translations:generate-json` - Generate translations in JSON format for the FileResources strategy.

For detailed usage and options, see the [Console](#console) section.

**Config**

In the [translation-web config](#translation-web-config) you can enable and configure this feature:

```php
'features' => [
    Feature\TranslationsConsoleCommands::class,
    // required when using new Feature\Translations::class
],
```

When [Managing Translations Across Multiple Apps](#manage-translations-across-multiple-apps), you may disable this feature for apps that only consume translations. Only the app responsible for generating, collecting, or publishing translations needs to have the console commands enabled.

### Machine Translation Feature

The **Machine Translation** feature enables automatic translation of missing or existing translation entries.  
It integrates with the [Translations Feature](#translations-feature) and can be used in several places:

- to automatically translate missing entries via the [Create For Locale Bulk Action](#create-for-locale-bulk-action)
- via the [Auto Translate Bulk Action](#auto-translate-bulk-action)
- to auto-translate values in the [Translations Feature](#translations-feature) CRUD form

This feature is **optional** and becomes active only when a machine translation service is configured and available.

**Config**

Enable and configure the feature in the [translation-web config](#translation-web-config):

```php
'features' => [
    Feature\MachineTranslation::class,
],
```

When enabled, the system automatically detects whether a real machine translator is available (not the `NullTranslator`).  
If no translator is configured, the feature remains inactive and UI options for auto-translation will not be shown.

**Onboarding Integration**

The Machine Translation feature uses the [Machine Translator](https://github.com/tobento-ch/app-machine-translator) configured in the onboarding service.

For details on how machine translation is integrated into onboarding, including the onboarding factory configuration, see the [Onboarding Service](#onboarding-service) section.

The onboarding service exposes a `supportsAutoTranslate()` method, which ensures that auto-translation is only available when a real translator is configured.

**Machine Translator Configuration**

Make sure you have configured the machine translator named **`translations`** in the [Machine Translator Config](https://github.com/tobento-ch/app-machine-translator#machine-translator-config).

It is recommended to use the `NonTranslatableStrategy\Placeholder()` strategy to preserve placeholders and variables during translation:

```php
'translators' => [

    'translations' => static function(Azure\MachineTranslatorFactory $factory): MachineTranslatorInterface {
        return $factory->createTranslator('translations', [
            'apiKey' => 'AZURE_TRANSLATOR_KEY',
            'region' => 'westeurope',
            'nonTranslatableStrategy' => new NonTranslatableStrategy\Placeholder(),
        ]);
    },
```

## Collectors

Collectors gather translation messages from different sources within your application and make them available to the translation repository.  
They can also be managed directly within the [Translations Feature](#translations-feature) in the web interface.

Collectors are configured in the [translation-web config](#translation-web-config) file, where you may enable or disable them as needed.

Once configured, you can run only selected collectors using the `translations:collect` command, or trigger collection directly through the [Translations Feature](#translations-feature) in the web interface.

### All Translations Collector

This collector gathers all translation messages from the resources configured by [App Translation](https://github.com/tobento-ch/app-translation), which sets up the [service-translation Translator](https://github.com/tobento-ch/service-translation#translator).  
It extracts messages from every translation resource registered with the Translator, making it the most complete collector.

**Config**

In the [translation-web config](#translation-web-config) you can enable and configure this collector.  
You may define **as many collectors as needed**, each with its own settings:

```php
'collectors' => [
    new Collector\AllTranslations(
        // A unique identifier for this collector:
        id: 'app-root',

        // A human-readable name shown in the UI:
        name: 'All Translations for the root app',

        // The application ID this collector should operate on:
        appId: 'root',

        // Only collect translations for these resource names (whitelist):
        //collectOnly: [],

        // Do not collect translations for these resource names (blacklist):
        //collectExcept: [],

        // Locales to load; if empty, defaults to translator locale and fallbacks:
        //locales: [],
    ),

    /* Example: another collector for a different app
    new Collector\AllTranslations(
        id: 'app-backend',
        name: 'All Translations for the backend app',
        appId: 'backend',
    ),
    */
],
```

**Notes on `collectOnly` and `collectExcept`**

Resource names come from the underlying [service-translation - Translator resources](https://github.com/tobento-ch/service-translation#resources).

You can inspect all available resources in your app using:

```
php ap translations:resources
```

This makes it easy to determine which resource names to include or exclude when configuring collectors.

**Example using `collectOnly`:**

```php
new Collector\AllTranslations(
    id: 'app-backend',
    name: 'All Translations for the backend app',
    appId: 'backend',
    collectOnly: ['*', 'validator'],
),
```

This configuration collects:
- all resources ('*')
- plus the specific validator resource

allowing fine-grained control over which translation sources are included.

## Publishing Strategies

Publishing strategies define **how and where translations are written** when they are published from the web interface or through console commands.  
Each app can choose the strategy that best fits its needs, whether writing translation files to disk, keeping them only in memory, or disabling publishing entirely.

Strategies are configured in the [translation-web config](#translation-web-config) file, and you may define different strategies per app.  
The selected strategy determines how collected or edited translations are exported and made available to your application.

### File Resources Strategy

The **FileResources** strategy publishes translations as JSON files on disk.  
It does this by registering a dedicated translation directory for the app and then
dispatching a queue job that generates all translation files asynchronously.

Translations are loaded **on demand** from the filesystem, which can be faster than
loading all translations into memory upfront - especially in larger applications.

#### How it works internally

**`publish(AppInterface $app)`**
- Registers a new translation directory under the app's directory.
- The directory name and folder name are configurable.
- Throws an exception if the directory already exists.
- Does **not** write any files itself.

**`schedulePublish(AppInterface $app)`**  
- Pushes a **unique** queue job (`GenerateJsonTranslationFilesJobHandler`) that:
  - creates the directory (if needed),
  - writes all **modified** published translations as JSON files.
- Uses the configured queue name (or the default queue if `null`).
- Sets retry attempts and job priority.

This ensures that file generation happens asynchronously and never blocks the UI.

#### Automatic Regeneration via Queue

When translations are modified through the [Translations Feature](#translations-feature) in the web interface, the system automatically dispatches a **queue job** that regenerates the JSON files for all apps using the FileResources strategy.  
This ensures that published translation files stay in sync with the latest changes without blocking the UI.

#### Config

Enable the FileResources strategy in the [translation-web config](#translation-web-config):

```php
'interfaces' => [
    Strategy\TranslationsPublishInterface::class => Strategy\FileResources::class,
],
```

You may also configure the strategy manually if you need to customize its behavior:

```php
'interfaces' => [
    Strategy\TranslationsPublishInterface::class =>
    static function(): Strategy\FileResources {
        return new Strategy\FileResources(
            // Queue name for dispatching the file-generation job.
            // Use null to use the default queue:
            queueName: null,

            // Folder name under the app directory where JSON files will be written:
            folderName: 'trans-custom',

            // Directory identifier used by the Dirs service:
            dirName: 'trans-custom',

            // Directory priority (higher overrides defaults):
            dirPriority: 10000,
        );
    },
],
```

### In Memory Resources Strategy

The **InMemoryResources** strategy publishes translations by attaching a lazy-loading `Resources` instance directly to the Translator.  
No files are written to disk, and no queue jobs are dispatched.

Instead, translations are loaded **on demand** from the `TranslationRepository` whenever the Translator requests a locale.  
This makes the strategy ideal for environments where:
- translations should remain fully dynamic,
- no file-based publishing is desired,
- or the application should always read the latest published translations directly from the repository (this strategy is the only one that does so).

Because translations are loaded only when needed, the strategy avoids loading all translations upfront. It also mirrors the behavior of file-based resources by merging any additional resource sources registered with the Translator.

#### How it works internally

**`publish(AppInterface $app)`**  
- Hooks into the Translator via `$app->on(TranslatorInterface::class, ...)`.  
- Ensures the Translator supports resource injection (`ResourcesAware`).  
- Attaches a custom `Resources` implementation that:
  - loads translations lazily per locale,
  - groups them by resource name, group, and priority,
  - merges them with any additional resource sources.

**Lazy loading behavior**  
- For each locale, the strategy performs **one repository lookup**:

  ```php
  $translations = $this->repository->findAllPublished(
      appId: $this->appId,
      locale: $locale
  );
  ```

  This results in one database query per locale (or equivalent, depending on the repository backend).  
  Each locale is loaded only once and cached for subsequent lookups.

**`schedulePublish(AppInterface $app)`**  
- Intentionally does nothing.
- No queue jobs are dispatched.
- Publishing is always immediate and in-memory.

#### Config

Enable the InMemoryResources strategy in the [translation-web config](#translation-web-config):

```php
'interfaces' => [
    Strategy\TranslationsPublishInterface::class => Strategy\InMemoryResources::class,
],
```

### Null Strategy

The **NullStrategy** is a no-operation translation publish strategy.  
It performs **no actions** when translations are published or scheduled for publishing.

This strategy is useful when:
- an application does not need published translations,
- publishing is handled entirely by another mechanism,
- or you want to disable publishing for a specific app without changing other configuration.

Because the strategy does nothing, it introduces no overhead and no side effects.

#### How it works internally

**`publish(AppInterface $app)`**  
- Intentionally empty.  
- Does not register directories, write files, or modify the Translator.

**`schedulePublish(AppInterface $app)`**  
- Also intentionally empty.  
- No queue jobs are dispatched.  
- No asynchronous work is scheduled.

This strategy effectively disables the publishing pipeline for the app.

#### Config

Enable the NullStrategy in the [translation-web config](#translation-web-config):

```php
'interfaces' => [
    Strategy\TranslationsPublishInterface::class => Strategy\NullStrategy::class,
],
```

## Handle Missing Translations

The **Missing Translation Handler** mechanism allows your application to react whenever a translation is not found at runtime.

By default, the translation system returns the original message or key passed to `trans()`.  
If you want to log missing translations, collect them during development, or trigger automated translation workflows, you can register your own handler.

Handlers are resolved through the container and executed whenever a translation for the requested locale does not exist.

**Config**

In the [translation-web config](#translation-web-config), you can bind your preferred handler implementation to the interface:

```php
'interfaces' => [
    MissingTranslationHandlerInterface::class => MissingHandler\Log::class,
    
    // If you don't want any handler, just comment out the line above.
    // MissingTranslationHandlerInterface::class => MissingHandler\Log::class,
],
```

### Available Handlers

#### Auto Translate Missing Translations

The `MissingHandler\AutoTranslate::class` handler automatically translates missing messages using the [tobento/app-machine-translator](https://github.com/tobento-ch/app-machine-translator) package.  
When a translation is not found, the handler queues a job that translates the message and stores it in the translation repository.  

**Requirements**

Make sure the following boots are booted:

```php
$app->boot(\Tobento\App\MachineTranslator\Boot\MachineTranslator::class);
$app->boot(\Tobento\App\Queue\Boot\Queue::class);
$app->boot(\Tobento\App\Cache\Boot\Cache::class); // needed for unique queue
```

When using the [Translations Feature](#translations-feature), all of these boots are already booted automatically.

**Example**

```php
use Tobento\App\AppInterface;

'interfaces' => [
    // Use the default AutoTranslate handler
    MissingTranslationHandlerInterface::class => MissingHandler\AutoTranslate::class,

    // Use a custom translator (e.g. the "null" translator)
    MissingTranslationHandlerInterface::class => static function(AppInterface $app) {
        return $app->make(MissingHandler\AutoTranslate::class, [
            // The queue name to dispatch translation jobs to (null = default queue)
            'queueName' => null,

            // Which events should trigger auto-translation:
            // 'missing' = missing translation
            // 'fallback' = fallback translation used
            // 'fallbackToDefault' = default locale used as fallback
            'auto' => ['missing'], // (default)

            // The machine-translator name to use (e.g. "null", "deepl", "google")
            'translatorName' => 'null',
        ]);
    },
],
```

The example above shows how to inject a custom translator.  
Using the `null` translator is useful when you want missing translations to be stored but not automatically translated.

#### Log Missing Translations

The `MissingHandler\Log::class` handler will log missing translations for debugging or monitoring.  

**Requirements**

Make sure the [App Logging Boot](https://github.com/tobento-ch/app-logging#logging-boot) is booted so the handler can write log entries.

```php
$app->boot(\Tobento\App\Logging\Boot\Logging::class);
```

Consider installing [app-logging-web](https://github.com/tobento-ch/app-logging-web) to browse and inspect log entries through a web interface.

**Example**

```php
'interfaces' => [
    MissingTranslationHandlerInterface::class => MissingHandler\Log::class,
],
```

You may configure which logger should be used in your ```app/config/logging.php``` file.  
If no alias is defined, the default logger will be used:

```php
'aliases' => [
    // Log missing translations using the "daily" logger:
    \Tobento\App\Translation\Web\MissingHandler\Log::class => 'daily',
    
    // Or disable logging entirely:
    \Tobento\App\Translation\Web\MissingHandler\Log::class => 'null',
],
```

#### Chain Multiple Handlers

The `Chain::class` handler from [service-translation](https://github.com/tobento-ch/service-translation#chain-handler) allows you to combine multiple missing-translation handlers and execute them in sequence.  
This is useful when you want to perform several actions for the same missing translation.

**Example**

```php
use Psr\Log\LoggerInterface;
use Tobento\App\AppInterface;
use Tobento\Service\MachineTranslator\MachineTranslatorInterface;

'interfaces' => [
    MissingTranslationHandlerInterface::class => static function(AppInterface $app) {
        return new \Tobento\Service\Translation\MissingHandler\Chain(
            new MissingHandler\Log(
                logger: $app->get(LoggerInterface::class)
            ),
            new MissingHandler\AutoTranslate(
                translator: $app->get(MachineTranslatorInterface::class)
            ),
        );
    },
],
```

## Onboarding Service

The **Onboarding Service** provides a high-level API for initializing and preparing translations when introducing a new locale or when synchronizing translation data across apps. It automates the most common onboarding tasks such as creating translation entries, auto-translating missing values, and publishing the results.

This service is especially useful when:

- adding a new locale to an existing application  
- synchronizing translations across multiple apps  
- preparing a locale for external translators  
- automating initial translation population using machine translation  
- ensuring all messages/keys exist before manual editing begins  

### Features

The Onboarding Service supports the following operations:

- **Create entries for a locale**  
  Ensures that all translation messages/keys exist for the target locale.  
  Missing entries are created automatically.

- **Auto-translate missing values (optional)**  
  Uses the configured machine translator to pre-fill empty or missing translations.  
  This step is safe by design:  
  - no overwriting of existing values  
  - no message/key creation  
  - only fills missing entries  

- **Publish translations (optional)**  
  Marks translations as `published` for the given locale and app.  
  This updates the published status of the entries but does **not** generate files or trigger any external publishing mechanism.

### Configuration

The `OnboardingInterface` is bound in the container with a factory that wires the
translation repository and (optionally) a machine translator.  
If a machine translator named `translations` is available, it will be used for
auto-translation, otherwise auto-translation is disabled.

```php
use Tobento\App\Translation\Onboarding\OnboardingInterface;
use Tobento\Service\Translation\TranslationRepositoryInterface;
use Tobento\Service\Translation\Machine\MachineTranslatorsInterface;

'interfaces' => [
    OnboardingInterface::class => static function(
        TranslationRepositoryInterface $translationRepository,
        null|MachineTranslatorsInterface $machineTranslators = null,
    ): OnboardingInterface {

        $machineTranslator = null;

        if ($machineTranslators && $machineTranslators->has('translations')) {
            $machineTranslator = $machineTranslators->get('translations');
        }

        return new Onboarding\Onboarding(
            translationRepository: $translationRepository,
            machineTranslator: $machineTranslator,
            autoTranslateInChunksOf: 20,
        );
    },
];
```

Auto‑translation in onboarding requires a machine translator named **`translations`**.  
For configuring the machine translator itself, see the [Machine Translation Feature](#machine-translation-feature) section.

### Onboarding Interface

The onboarding functionality is provided through the `OnboardingInterface`, which exposes a set of dedicated methods for preparing and managing translations for a given locale and app. These methods cover the full onboarding workflow-from creating missing entries to auto-translating and publishing them, as well as a flexible query-based auto-translation method used by bulk actions.

```php
use Tobento\App\Translation\Onboarding\OnboardingInterface;

$onboarding = $app->get(OnboardingInterface::class);
```

#### Create missing translation entries

Creates translation entries for all messages/keys that do not yet exist for the given locale.  
Existing or user-edited translations are never overwritten.

```php
$created = $onboarding->createTranslationEntries(
    locale: 'fr',
    appId: 'backend',
);
```

#### Check if auto-translation is supported

Determines whether a machine translator is available and configured.

```php
if ($onboarding->supportsAutoTranslate()) {
    // Auto‑translation is available
}
```

You can also retrieve the actual translator instance (or `null` if none is configured):

```php
use Tobento\Service\MachineTranslator\MachineTranslatorInterface;

$translator = $onboarding->machineTranslator();

if ($translator !== null) {
    // A machine translator is available
    var_dump($translator instanceof MachineTranslatorInterface);
    // bool(true)
}
```

#### Auto-translate missing entries

Automatically fills empty or missing translations using the configured machine translator.
Existing translations are never overwritten.

```php
$autoTranslated = $onboarding->autoTranslateEntries(
    locale: 'fr',
    appId: 'backend',
);
```

#### Publish translations

Marks translations as `published` for the given locale and app.
This updates the published status but does not generate or write any files.

```php
$published = $onboarding->publishTranslations(
    locale: 'fr',
    appId: 'backend',
);
```

#### Advanced Auto-Translation (Query-Based)

In addition to the locale-based onboarding workflow, the `OnboardingInterface` also provides a flexible, query-based auto-translation method. This is primarily used by bulk actions to translate arbitrary sets of translation entries.

**Auto-translate entries by query**

Automatically translates translation entries matching the given query parameters.  
Unlike `autoTranslateEntries()`, this method is not restricted to a single locale or app and does not assume any onboarding workflow.

```php
$autoTranslated = $onboarding->autoTranslateEntriesBy(
    where: ['id' => ['in' => [1, 2, 3]]],
    orderBy: ['key' => 'ASC'],
    limit: [],
);
```

The default onboarding implementation supports the standard [where parameters](https://github.com/tobento-ch/service-repository-storage#where-parameters) used by the [Repository Storage package](https://github.com/tobento-ch/service-repository-storage), allowing flexible filtering of translation entries.

### When to Use the Onboarding Service

Use the Onboarding Service when:

- you add a new locale to your application  
- you want to pre-fill translations before manual editing  
- you want to ensure all messages/keys exist for a locale  
- you want to automate the initial translation setup  
- you want to run onboarding as a background job with UI feedback  

### Relation to Bulk Actions

Several bulk actions in the Translation Web internally use the onboarding functionality:

- [Create For Locale Bulk Action](#create-for-locale-bulk-action)  
- [Auto Translate Bulk Action](#auto-translate-bulk-action)  
- [Edit Status Bulk Action](#edit-status-bulk-action)

These bulk actions provide a UI-driven workflow, while the `OnboardingInterface` offers the same capabilities programmatically for automation, CLI scripts, or multi-app workflows.

The onboarding functionality provides a reliable and automated way to prepare translations for new locales, ensuring a smooth transition from initial key creation to fully published translation resources.

## Console

### Collect Translations Command

Use the following command to collect translations from all configured collectors or only selected ones:

**Collect all translations**

```
php ap translations:collect
```

**Available Options**

| Option            | Description                                      |
| ----------------- | ------------------------------------------------ |
| `--collectorId[]` | Runs only the collectors matching the given IDs. |

Collectors are configured in the [translation-web config](#translation-web-config) file.  
For more details, see the [Collectors](#collectors) section.

### Generate JSON Translation Files Command

Use the following command to generate translation files in JSON format for the FileResources strategy:

**Generate JSON translation files**

```
php ap translations:generate-json
```

**Available Options**

| Option        | Description                                              |
| ------------- | -------------------------------------------------------- |
| `--appId[]`   | Only generate files for the specified app IDs.           |

The generated files are used by the FileResources strategy to provide JSON-based translation resources.

JSON files are generated **only for apps where the [File Resources Strategy](#file-resources-strategy) is enabled**.  
If the app does not use the [File Resources Strategy](#file-resources-strategy), or required services are missing, no files will be generated.

## Learn More

### Manage Translations Across Multiple Apps

When working with multiple [Apps](https://github.com/tobento-ch/apps), you may want to manage or publish translations across all of them from a single web interface.  
To achieve this, ensure that each app uses the **same translation repository connection** in its [config file](#translation-web-config).

#### Example: App with Full Translation Management

In an app that manages translations (e.g., an admin or backend app), you typically enable both the web interface and the console commands.  
For example, in an app using the [App Backend](https://github.com/tobento-ch/app-backend), you might configure:

```
'features' => [
    Feature\Translations::class,
    Feature\TranslationsConsoleCommands::class,
],

'interfaces' => [
    // You may choose the publishing strategy:
    //Strategy\TranslationsPublishInterface::class => Strategy\NullStrategy::class,
    Strategy\TranslationsPublishInterface::class => Strategy\FileResources::class,
    //Strategy\TranslationsPublishInterface::class => Strategy\InMemoryResources::class,
    
    // Configure repository using shared storage:
    TranslationRepositoryInterface::class =>
    static function(DatabasesInterface $databases): TranslationRepositoryInterface {
        return new TranslationStorageRepository(
            storage: $databases->default('shared:storage')->storage()->new(),
            table: 'translations',
        );
    },
],
```

#### Example: Another App That Only Uses or Publishes Translations

In another app where you only want to *use* or *publish* translations (but not manage them):

```
'features' => [
    // no feature at all.
],

'interfaces' => [
    // Choose the publishing strategy:
    Strategy\TranslationsPublishInterface::class => Strategy\FileResources::class,
    //Strategy\TranslationsPublishInterface::class => Strategy\InMemoryResources::class,
    
    // Configure repository using shared storage:
    TranslationRepositoryInterface::class =>
    static function(DatabasesInterface $databases): TranslationRepositoryInterface {
        return new TranslationStorageRepository(
            storage: $databases->default('shared:storage')->storage()->new(),
            table: 'translations',
        );
    },
],
```

#### Shared Storage Configuration

Finally, in the [database config file](https://github.com/tobento-ch/app-database#database-config) of both apps, configure the `shared:storage` database:

```php
'defaults' => [
    'pdo' => 'mysql',
    'storage' => 'file',
    'shared:storage' => 'shared:file',
],

'databases' => [
    'shared:file' => [
        'factory' => \Tobento\Service\Database\Storage\StorageDatabaseFactory::class,
        'config' => [
            'storage' => \Tobento\Service\Storage\JsonFileStorage::class,
            'dir' => directory('app:parent').'storage/database/file/',
        ],
    ],
],
```

By using a shared storage connection, all translations from every app are stored in the same repository, allowing them to be viewed, edited, and published centrally through the [Translations Feature](#translations-feature) web interface.

### Browser Notification for Background Jobs

The following actions perform background jobs:

- [Collect Bulk Action](#collect-bulk-action)
- [Create For Locale Bulk Action](#create-for-locale-bulk-action)
- [Auto Translate Bulk Action](#auto-translate-bulk-action)
- [Edit Status Bulk Action](#edit-status-bulk-action)
- [Delete Bulk Action](#delete-bulk-action)
- [Export Bulk Action](#export-bulk-action)
- [Import Bulk Action](#import-bulk-action)

These actions run asynchronously in the queue and notify the user once they have finished by using the [Browser Channel](https://github.com/tobento-ch/app-notifier#browser-stream-feature).

To receive browser notifications, the user must have the `notifications.browser` ACL permission.  
All other functionality is already set up and works out of the box.

You may set the permission manually (see [ACL Service](https://github.com/tobento-ch/service-acl#permissions)), or, if you are using the [App Backend](https://github.com/tobento-ch/app-backend), you can assign this permission directly on the Roles or Users page.

# Credits

- [Tobias Strub](https://www.tobento.ch)
- [All Contributors](../../contributors)