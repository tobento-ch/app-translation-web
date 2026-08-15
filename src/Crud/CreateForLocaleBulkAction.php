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

namespace Tobento\App\Translation\Web\Crud;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Tobento\App\Crud\Action\ActionInterface;
use Tobento\App\Crud\Action;
use Tobento\App\Crud\ActionProcessorInterface;
use Tobento\App\Crud\Entity\Entity;
use Tobento\App\Crud\Exception\ActionNotFoundException;
use Tobento\App\Crud\Exception\ActionProcessException;
use Tobento\App\Crud\Field;
use Tobento\App\Crud\Field\FieldInterface;
use Tobento\App\Crud\Field\Fields;
use Tobento\App\Crud\Field\FieldsInterface;
use Tobento\App\Crud\FilterProcessorInterface;
use Tobento\App\Crud\Html\Message;
use Tobento\App\Crud\Input\Input;
use Tobento\App\Translation\Web\Collector\CollectorsInterface;
use Tobento\App\Translation\Web\Onboarding\OnboardingInterface;
use Tobento\App\Translation\Web\Queue\CreateForLocaleJobHandler;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\App\User\UserInterface;
use Tobento\Service\Acl\AclInterface;
use Tobento\Service\Language\LanguagesInterface;
use Tobento\Service\Queue\Job;
use Tobento\Service\Queue\QueueInterface;
use Tobento\Service\Requester\RequesterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\View\ViewInterface;
use function Tobento\App\Translation\trans;

/**
 * Bulk action for creating translation entries for a specific locale.
 *
 * Creates missing translation entries for the selected locale without
 * overwriting existing ones. Newly created entries may optionally be
 * pre‑filled using machine translation. The process runs as a queued job.
 */
class CreateForLocaleBulkAction extends Action\AbstractAction implements Action\BulkActionInterface
{
    use Action\HasActionProcessor;
    use Action\Traits\HandleBulk;
    use Action\Traits\InteractsWithRequest;
    use Action\Traits\ConfiguresModal;
    
    /**
     * Create a new instance.
     *
     * @param string $name Must be sluggable and contain only [a-z-] characters.
     * @param string|null $title Optional display title for the action.
     * @param string|null $queueName Optional queue name where the collect job will be dispatched.
     */
    public function __construct(
        protected string $name = 'create-locale',
        null|string $title = null,
        protected null|string $queueName = null,
    ) {
        if ((bool) preg_match('/^[a-z-_.]+$/u', $name) === false) {
            throw new \InvalidArgumentException(
                sprintf('The name %s must only contain [a-z-_.] characters', $name)
            );
        }
        
        $this->title = $title ?: $name;
        $this->route('{name}.bulk', function(): array {
            return ['name' => $this->name()];
        });
        
        $this->linkToAction('index');
        
        $this->view('crud/bulk/modal');
        $this->modalButtonLabel(trans('Create'));
    }
    
    /**
     * Returns the name. Must be sluggable and only of [a-z-] characters.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }
    
    /**
     * Returns a namespaced field name for this action.
     *
     * @param string $suffix Field-specific suffix.
     * @return string
     */
    public function fieldName(string $suffix): string
    {
        return $this->name() . '_' . $suffix;
    }    
    
    /**
     * Returns whether to display the button to perform the action.
     *
     * @return bool
     */
    public function displayButton(): bool
    {
        return true;
    }
    
    /**
     * Returns the handler processing the action.
     *
     * @return callable(mixed...): \Psr\Http\Message\ResponseInterface
     */
    public function getHandler(): callable
    {
        return [$this, 'handle'];
    }
    
    /**
     * Handle action.
     *
     * @param ActionProcessorInterface $actionProcessor
     * @param RequesterInterface $requester
     * @param ResponserInterface $responser
     * @return ResponseInterface
     */
    public function handle(
        ActionProcessorInterface $actionProcessor,
        RequesterInterface $requester,
        ResponserInterface $responser,
    ): ResponseInterface {
        return $this->handleBulk(
            action: $this,
            actionProcessor: $actionProcessor,
            requester: $requester,
            responser: $responser,
        );
    }
    
    /**
     * Returns the process bulk action.
     *
     * @return callable
     */
    public function getBulkProcessAction(): callable
    {
        return [$this, 'processBulk'];
    }
    
    /**
     * Process bulk action.
     *
     * @param RequesterInterface $requester
     * @param AclInterface $acl
     * @param QueueInterface $queue
     * @param ResponserInterface $responser
     * @return void
     * @throws ActionProcessException
     * @psalm-suppress RedundantCondition
     * @psalm-suppress NoValue
     */
    public function processBulk(
        RequesterInterface $requester,
        AclInterface $acl,
        QueueInterface $queue,
        ResponserInterface $responser
    ): void {
        // Validate input using a Store action
        $storeAction = new Action\Store();
        $storeAction->setController($this->controller());
        $this->actionProcessor()->preprocessAction(action: $storeAction);

        $storeAction->setInput($this->fetchInput(requester: $requester, action: $storeAction, fresh: true));

        $fields = Fields::fromIterable($this->configureFields($storeAction));
        $storeAction->setFields($fields);

        $this->actionProcessor()->processFields(
            action: $storeAction,
            entity: new Entity()
        );

        // Extract validated input
        $input = $storeAction->getInput();
        
        $autoTranslate = $input->get($this->fieldName('auto_translate')) === '1';
        if ($acl->cant('translations.auto-translate')) {
            $autoTranslate = false;
        }
        
        $publish = $input->get($this->fieldName('publish')) === '1';
        if ($acl->cant('translations.publish')) {
            $publish = false;
        }
        
        $user = $requester->request()->getAttribute(UserInterface::class);
        
        // Queue onboarding job
        $this->pushToQueue(
            queue: $queue,
            payload: [
                'locale' => $input->get($this->fieldName('locale')),
                'app_id' => $input->get($this->fieldName('app_id')),
                'user_id' => $user instanceof UserInterface ? $user->id() : null,
                'auto_translate' => $autoTranslate,
                'publish' => $publish,
            ],
        );

        $responser->messages()->add(
            level: 'success',
            message: trans('Locale onboarding has started and will continue in the background.'),
        );
    }
    
    /**
     * Pushes a collect job to the queue.
     *
     * @param QueueInterface $queue
     * @param array<string, mixed> $payload
     * @return void
     */
    protected function pushToQueue(
        QueueInterface $queue,
        array $payload,
    ): void {
        $job = new Job(
            name: CreateForLocaleJobHandler::class,
            payload: $payload,
        );

        if ($this->queueName) {
            $job->queue($this->queueName);
        }

        $job->retry(3);

        // Lower priority so it doesn't block important jobs
        $job->priority(-50);

        $queue->push($job);
    }
    
    /**
     * Returns the html of action. MUST be escaped.
     *
     * @param ViewInterface $view
     * @return string
     */
    public function render(ViewInterface $view): string
    {
        $view->asset('assets/crud/live.js')->attr('type', 'module');
        
        $indexAction = $this->actions()->get('index');
        
        if (is_null($indexAction)) {
            return '';
        }
        
        $createAction = new Action\Create();
        $createAction->setContainer($indexAction->container());
        $fields = Fields::fromIterable($this->configureFields($createAction));
        
        $createAction->setFields($fields);
        $this->actionProcessor->processFields(action: $createAction, entity: new Entity());
        $this->setFields($createAction->fields());
        
        return $view->render(
            view: $this->getView(),
            data: [
                'action' => $this,
            ],
        );
    }
    
    /**
     * Returns the configured fields.
     *
     * @param ActionInterface $action
     * @return iterable<FieldInterface>|FieldsInterface
     */
    protected function configureFields(ActionInterface $action): iterable|FieldsInterface
    {
        $container = $action->container();
        
        yield new Field\Html(name: $this->fieldName('info'))
            ->group(trans('Create'))
            ->content(html: new Message(
                text: trans('Creates missing translation entries for the selected locale and app based on existing translation messages (keys), without overwriting any existing translations.'),
                info: true,
                attributes: ['class' => 'mt-s mb-m'],
            ));
        
        $acl = $action->container()->get(AclInterface::class);
        $translationRepository = $action->container()->get(TranslationRepositoryInterface::class);
        $languages = $this->resolveLanguages($action->container());
        
        yield new Field\Select(
            name: $this->fieldName('locale'),
            label: trans('Locale'),
        )
            ->group(trans('Create'))
            ->options($languages->column('key', 'key'))
            ->validate('required');
        
        yield new Field\Select(
            name: $this->fieldName('app_id'),
            label: trans('App ID'),
        )
            ->options($translationRepository->distinctValues('app_id'))
            ->group(trans('Create'))
            ->validate('required');
        
        if (
            $acl->can('translations.auto-translate')
            && $container->get(OnboardingInterface::class)->supportsAutoTranslate()
        ) {
            yield new Field\Radios(
                name: $this->fieldName('auto_translate'),
                label: trans('Auto Translate'),
            )
                ->group('Create')
                ->options(['0' => trans('No'), '1' => trans('Yes')])
                ->selected(value: '0', action: 'create|edit')
                ->displayInline()
                ->infoText(trans('Automatically translate missing entries using machine translation, otherwise existing translations from the source locale are used.'));
        }
        
        if ($acl->can('translations.publish')) {
            yield new Field\Radios(
                name: $this->fieldName('publish'),
                label: trans('Publish'),
            )
                ->group('Create')
                ->options(['0' => trans('No'), '1' => trans('Yes')])
                ->selected(value: '0', action: 'create|edit')
                ->displayInline()
                ->infoText(trans('Set translation entries to published status.'));
        }
    }
    
    /**
     * Returns the resolved languages for the language field.
     *
     * @param ContainerInterface $container
     * @return LanguagesInterface
     */
    protected function resolveLanguages(ContainerInterface $container): LanguagesInterface
    {
        return $container->get(LanguagesInterface::class);
    }
}