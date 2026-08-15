<?php

/**
 * TOBENTO
 *
 * @copyright   Tobias Strub, TOBENTO
 * @license     MIT License, see LICENSE file distributed with this source code.
 * @author      Tobias Strub
 * @link        https://www.tobento.ch
 */

declare(strict_types=1);
 
namespace Tobento\App\Translation\Web\Controller;

use Tobento\App\AppInterface;
use Tobento\App\Crud\AbstractCrudController;
use Tobento\App\Crud\Action;
use Tobento\App\Crud\Action\ActionInterface;
use Tobento\App\Crud\Action\ActionsInterface;
use Tobento\App\Crud\Button;
use Tobento\App\Crud\Entity\Entity;
use Tobento\App\Crud\Entity\EntityInterface;
use Tobento\App\Crud\Field;
use Tobento\App\Crud\Field\FieldInterface;
use Tobento\App\Crud\Field\FieldsInterface;
use Tobento\App\Crud\Filter;
use Tobento\App\Crud\Filter\FiltersInterface;
use Tobento\App\Crud\Filter\FilterInterface;
use Tobento\App\Crud\Html\Message;
use Tobento\App\Crud\Input\InputInterface;
use Tobento\App\Http\Exception\ForbiddenException;
use Tobento\App\Http\Exception\HttpException;
use Tobento\App\ImportExport\Crud\ExportBulkAction;
use Tobento\App\ImportExport\Crud\ImportBulkAction;
use Tobento\App\Translation\Web\Crud\AutoTranslateBulkAction;
use Tobento\App\Translation\Web\Crud\CollectBulkAction;
use Tobento\App\Translation\Web\Crud\CreateForLocaleBulkAction;
use Tobento\App\Translation\Web\Onboarding\OnboardingInterface;
use Tobento\App\Translation\Web\Queue\SchedulePublishTranslationsJobHandler;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\App\User\AddressRepositoryInterface;
use Tobento\Service\Acl\AclInterface;
use Tobento\Service\Collection\Collection;
use Tobento\Service\Queue\Job;
use Tobento\Service\Queue\QueueInterface;
use Tobento\Service\Repository\RepositoryInterface;
use function Tobento\App\Translation\trans;

class TranslationCrudController extends AbstractCrudController
{
    /**
     * Must be unique, lowercase and only of [a-z-] characters.
     */
    public const RESOURCE_NAME = 'translations';
    
    /**
     * Create a new instance.
     *
     * @param TranslationRepositoryInterface $repository
     * @param OnboardingInterface $onboarding
     * @param AclInterface $acl
     * @param AppInterface $app
     * @param null|string $queueName
     */
    public function __construct(
        TranslationRepositoryInterface $repository,
        protected OnboardingInterface $onboarding,
        protected AclInterface $acl,
        protected AppInterface $app,
        protected null|string $queueName = null,
    ) {
        $this->repository = $repository;
    }
    
    /**
     * Returns the configured fields.
     *
     * @param ActionInterface $action
     * @return iterable<FieldInterface>|FieldsInterface
     */
    protected function configureFields(ActionInterface $action): iterable|FieldsInterface
    {
        yield new Field\PrimaryId(name: 'id');

        yield new Field\Select(name: 'status', label: trans('Status'))
            ->group(trans('Translation'))
            ->options(fn(): array => $this->getStatusOptions(action: $action, currentStatus: $action->entity()->get('status')))
            ->formatValue(new Field\Formatter\Badge(classes: [
                'imported' => 'text-info',
                'draft' => 'text-error',
                'published' => 'text-success',
                'review' => 'text-warning',
            ]))
            ->validate('required');
        
        yield new Field\Textarea(name: 'message', label: trans('Message / Key'))
            ->group(trans('Translation'))
            ->optionalText('')
            ->disabled();
        
        $translationField = new Field\Textarea(name: 'translation', label: trans('Translation'))
            ->group(trans('Translation'))
            ->validate('required|string|htmlclean|maxLen:10000');
        
        if (
            $this->acl->can('translations.auto-translate')
            && $this->onboarding->supportsAutoTranslate()
        ) {
            $translationField->machineTranslator(
                allowNonTranslatable: true,
                translator: $this->onboarding->machineTranslator()?->name(),
                attributes: function (Field\Textarea $field, string $actionName): array {
                    $id = $field->entity()->get('id');
                    $locale = $field->entity()->get('resource_locale', '');

                    // Format locale label
                    $localeLabel = $locale !== ''
                        ? trans('Locale: :locale', [':locale' => $locale])
                        : '';
                    
                    // Row‑scoped selectors
                    $fromSel = sprintf('[data-entity-id="%s"] [data-field="message"]', $id);
                    $toSel = sprintf('[data-entity-id="%s"] textarea[name="translation"]', $id);

                    $from = $actionName === 'index'
                        ? ['from_selector' => $fromSel, 'to_selector' => $toSel]
                        : ['from' => 'message'];
                    
                    $field->infoText($localeLabel);
                    
                    return [
                        'class' => 'button text-xxs mb-xs',
                        'data-machine-translator' => [
                            'from_first' => null,
                            'locale' => $locale,
                        ] + $from,
                    ];
                },
            );
        }
        
        yield $translationField;
        
        yield new Field\Text(name: 'translated_by', label: trans('Translated By'))
            ->group(trans('Translation'))
            ->optionalText('')
            ->disabled();
        
        yield new Field\Textarea(name: 'origin_translation', label: trans('Original Translation'))
            ->group(trans('Translation'))
            ->optionalText('')
            ->disabled();
        
        yield new Field\Text(name: 'origin_translated_by', label: trans('Original Translator'))
            ->group(trans('Translation'))
            ->optionalText('')
            ->disabled();

        yield new Field\Select(name: 'user_id', label: trans('Translated by User'))
            ->group(trans('Translation'))
            ->options(fn (AddressRepositoryInterface $repo) => $repo->findColumn('name', 'user_id', ['key' => 'primary']))
            ->emptyOption(value: 'none', label: '---')
            ->optionalText('')
            ->disabled();

        yield new Field\Radios(name: 'is_translation_missing', label: trans('Missing Translation'))
            ->group(trans('Translation'))
            ->options(['0' => trans('No'), '1' => trans('Yes')])
            ->displayInline()
            ->formatValue(new Field\Formatter\Badge(
                classes: ['0' => 'text-success', '1' => 'text-error'],
            ))
            ->optionalText('')
            ->disabled();
        
        yield new Field\Textarea(name: 'notes', label: trans('Notes'))
            ->group(trans('Translation'));
        
        if (in_array($action->name(), ['index', 'show'])) {
            yield new Field\Text(name: 'created_at', label: trans('Created At'))
                ->type('datetime-local')
                ->group(trans('Translation'))
                ->optionalText('')
                ->formatValue(new Field\Formatter\Date(format: 'EEEE, dd. MMMM yyyy, HH:mm'));
        }
        
        yield new Field\Text(name: 'app_id', label: trans('App ID'))
            ->group(trans('Resource'))
            ->optionalText('')
            ->disabled();
        
        yield new Field\Text(name: 'resource_name', label: trans('Resource Name'))
            ->group(trans('Resource'))
            ->optionalText('')
            ->disabled();
        
        yield new Field\Select(name: 'resource_locale', label: trans('Resource Locale'))
            ->group(trans('Resource'))
            ->options($this->translationRepository()->distinctValues('resource_locale'))
            ->optionalText('')
            ->disabled();
        
        yield new Field\Text(name: 'resource_group', label: trans('Resource Group'))
            ->group(trans('Resource'))
            ->optionalText('')
            ->disabled();
        
        yield new Field\Text(name: 'resource_priority', label: trans('Resource Priority'))
            ->group(trans('Resource'))
            ->type('number')
            ->optionalText('')
            ->disabled();
        
        yield new Field\Text(name: 'resource_filename', label: trans('Resource Filename'))
            ->group(trans('Resource'))
            ->optionalText('')
            ->disabled();
    }
    
    /**
     * Returns the configured actions.
     *
     * @return iterable<ActionInterface>|ActionsInterface
     */
    protected function configureActions(): iterable|ActionsInterface
    {
        yield new Action\Index(title: trans('Translations'))
            ->removeButton('copy')
            ->groupButtons(
                except: ['edit'],
                button: new Button\Dropdown(label: '', icon: 'dots', group: 'entity')
                    ->name('more')
                    ->raw(),
            )
            ->displayButtonIf('edit', function(EntityInterface $entity) {
                if ($this->acl->can('translations.publish')) {
                    return true;
                }

                return in_array($entity->get('status'), ['imported', 'draft', 'review']);
            })
            ->displayButtonIf('delete', $this->acl->can('translations.delete'));

        yield new Action\Edit(title: trans('Edit Translation'));

        yield new Action\Update();

        yield new Action\Show(title: trans('Translation Details'));

        yield new Action\ShowJson();

        yield new Action\Delete();

        if ($this->acl->can('translations.collect')) {
            yield new CollectBulkAction(
                title: trans('Collect'),
                queueName: $this->queueName,
            );
        }
        
        if ($this->acl->can('translations.create')) {
            yield new CreateForLocaleBulkAction(
                title: trans('Create for Locale'),
                queueName: $this->queueName,
            );
        }
        
        if (
            $this->acl->can('translations.auto-translate')
            && $this->onboarding->supportsAutoTranslate()
        ) {
            yield new AutoTranslateBulkAction(
                title: trans('Auto Translate'),
                queueName: $this->queueName,
            );
        }
        
        if ($this->acl->can('translations.edit')) {
            yield new Action\BulkEdit(name: 'edit-status', title: trans('Edit Status'))
                ->field('status');
        }

        if ($this->acl->can('translations.delete')) {
            yield new Action\BulkDelete(title: trans('Delete'));
        }
        
        if ($this->acl->can('translations.export')) {
            yield new ExportBulkAction(
                name: 'export',
                title: trans('Export'),
            );
        }

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
    }
    
    /**
     * Returns the configured filters.
     *
     * @param ActionInterface $action
     * @return iterable<FilterInterface>|FiltersInterface
     */
    protected function configureFilters(ActionInterface $action): iterable|FiltersInterface
    {
        return [
            ...new Filter\Fields()
                ->fields($action->fields())
                ->except('app_id')
                ->toFilters(),
            
            new Filter\Select(name: 'app_id', field: 'app_id')
                ->options($this->translationRepository()->distinctValues('app_id'))
                ->group('field'),
            
            new Filter\FieldsSortOrder(),
            
            new Filter\ModalButton()->group('header'),
            
            new Filter\Group(name: 'group-columns')->group('modal')->label(trans('Columns'))->open(false),
            
            new Filter\Columns()
                ->group('group-columns')
                ->default('status', 'message', 'translation', 'resource_locale', 'app_id', 'actions'),
            
            new Filter\Group(name: 'group-editable-columns')
                ->group('modal')
                ->label(trans('Editable Columns'))
                ->open(false),
            
            new Filter\EditableColumns('translation', 'status')->group('group-editable-columns'),
            
            new Filter\Group(name: 'group-pagination')->group('modal')->label(trans('Pagination'))->open(false),
            
            new Filter\PaginationItemsPerPage()
                ->group('group-pagination')
                ->open(false),
            
            new Filter\Pagination()->group('footer'),
        ];
    }
    
    /**
     * Determines if action is processable.
     *
     * @param ActionInterface $action
     * @return void
     * @throws \Throwable
     */
    public function isActionProcessable(ActionInterface $action): void
    {
        if (in_array($action->name(), ['index'])) {
            return;
        }
        
        // Skip entity-based checks for bulk actions
        if ($action instanceof Action\BulkActionInterface) {
            return;
        }
        
        // Non-publishers can only edit when 'draft' or 'review' status
        $status = $action->entity()->get('status');
        
        if ($status !== 'published') {
            $status = $action->getInput()->get('status', $action->entity()->get('status'));
        }
        
        if (
            in_array($action->name(), ['edit', 'update', 'delete'])
            && $this->acl->cant('translations.publish')
            && !in_array($status, ['imported', 'draft', 'review'])
        ) {
            throw new ForbiddenException(trans('You can only edit or delete resources that are in Imported, Draft or Review status.'));
        }
        
        parent::isActionProcessable(action: $action);
    }
    
    /**
     * Update entity.
     *
     * @param int|string $id
     * @param array $attributes
     * @param EntityInterface $entity
     * @return object The updated entity
     */
    public function updateEntity(int|string $id, array $attributes, EntityInterface $entity): object
    {
        // Determine if translation is modified
        $isModified = $this->determineIsModified($attributes, $entity);
        
        if ($isModified) {
            $attributes['is_modified'] = true;
        }
        
        $updated = $this->repository()->updateById(
            id: $id,
            attributes: $attributes,
        );
        
        // Only after the entity is updated, schedule publish
        if ($isModified) {
            $this->schedulePublishForApp($entity);
        }
        
        return $updated;
    }
    
    /**
     * Delete entity.
     *
     * @param int|string $id
     * @param EntityInterface $entity
     * @return void
     */
    public function deleteEntity(int|string $id, EntityInterface $entity): void
    {
        // Delete the translation entry
        $this->repository()->deleteById(id: $id);

        // Schedule publish for the app
        $this->schedulePublishForApp($entity);
    }
    
    /**
     * Returns the status options.
     *
     * @param ActionInterface $action
     * @param null|string $currentStatus
     * @return array<string, string>
     */
    protected function getStatusOptions(ActionInterface $action, ?string $currentStatus): array
    {
        $options = [
            'imported' => trans('Imported'),
            'draft' => trans('Draft'),
            'published' => trans('Published'),
            'review' => trans('Pending Review'),
        ];

        // Index/show always show all statuses
        if (in_array($action->name(), ['index', 'show'], true)) {
            return $options;
        }

        // Publishers can choose any status
        if ($this->acl->can('translations.publish')) {
            return $options;
        }

        // Allowed statuses for non-publishers
        $allowed = ['imported', 'draft', 'review'];

        // Always include the current status (even if published)
        if ($currentStatus && !in_array($currentStatus, $allowed, true)) {
            $allowed[] = $currentStatus;
        }

        return array_intersect_key($options, array_flip($allowed));
    }

    /**
     * Determine if the translation has been modified.
     *
     * Compares incoming attribute values with the existing entity values
     * and returns true when a change is detected.
     *
     * @param array $attributes The new attribute values
     * @param EntityInterface $entity The existing entity
     * @return bool True if modified, otherwise false
     */
    protected function determineIsModified(array $attributes, EntityInterface $entity): bool
    {
        $oldMessage = $entity->get('message');
        $oldTranslation = $entity->get('translation');
        $oldStatus = $entity->get('status');
        $new = new Collection($attributes);
        
        // Determine if modified
        return (
            $new->get('message', $oldMessage) !== $oldMessage ||
            $new->get('translation', $oldTranslation) !== $oldTranslation ||
            $new->get('status', $oldStatus) !== $oldStatus
        );
    }
    
    /**
     * Dispatch a job to schedule a publish for the app
     * associated with the given translation entity.
     *
     * @param EntityInterface $entity
     * @return void
     */
    protected function schedulePublishForApp(EntityInterface $entity): void
    {
        $queue = $this->app->get(QueueInterface::class);

        $job = new Job(
            name: SchedulePublishTranslationsJobHandler::class,
            payload: [
                'app_id' => $entity->get('app_id'),
                'translation_id' => $entity->get('id'),
            ],
        );

        if ($this->queueName) {
            $job->queue($this->queueName);
        }

        // Prevent duplicate publish jobs
        $job->unique(id: 'schedule:publish:translations:'.$entity->get('app_id'));

        $job->retry(3);
        $job->priority(-50);

        $queue->push($job);
    }
    
    /**
     * Returns the translation repository.
     *
     * @return TranslationRepositoryInterface
     */
    public function translationRepository(): TranslationRepositoryInterface
    {
        return $this->repository;
    }
}