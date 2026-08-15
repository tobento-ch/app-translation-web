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
use Tobento\App\Translation\Web\Queue\CollectTranslationsJobHandler;
use Tobento\App\User\UserInterface;
use Tobento\Service\Queue\Job;
use Tobento\Service\Queue\QueueInterface;
use Tobento\Service\Requester\RequesterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\View\ViewInterface;
use function Tobento\App\Translation\trans;

/**
 * Bulk action for collecting translation messages/keys.
 *
 * This action triggers one or more registered collectors to scan the
 * application for translation messages (keys). Missing messages are added
 * to the translation repository, existing keys may be updated if
 * unchanged, and user-edited translations remain untouched. The
 * collection process is dispatched as a queued job and runs in the
 * background.
 */
class CollectBulkAction extends Action\AbstractAction implements Action\BulkActionInterface
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
        protected string $name = 'collect',
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
        $this->modalButtonLabel(trans('Collect'));
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
     * @param QueueInterface $queue
     * @param ResponserInterface $responser
     * @return void
     * @throws ActionProcessException
     * @psalm-suppress RedundantCondition
     * @psalm-suppress NoValue
     */
    public function processBulk(
        RequesterInterface $requester,
        QueueInterface $queue,
        ResponserInterface $responser
    ): void {
        // Process action for validation e.g.
        $storeAction = new Action\Store();
        $storeAction->setController($this->controller());
        $this->actionProcessor()->preprocessAction(action: $storeAction);
        
        $storeAction->setInput($this->fetchInput(requester: $requester, action: $storeAction, fresh: true));
        
        $fields = Fields::fromIterable($this->configureFields($storeAction));
        $storeAction->setFields($fields);
        
        $this->actionProcessor()->processFields(action: $storeAction, entity: new Entity());
        
        // Get input data
        $input = $storeAction->getInput();

        $collectorIds = $input->get($this->fieldName('collector_ids'), []);
        
        // Push to queue
        $this->pushToQueue(
            queue: $queue,
            collectorIds: $collectorIds,
            user: $requester->request()->getAttribute(UserInterface::class),
        );

        $responser->messages()->add(
            level: 'success',
            message: trans('Collecting translations has started and will continue in the background.'),
        );
    }
    
    /**
     * Pushes a collect job to the queue.
     *
     * @param QueueInterface $queue
     * @param array<int, string> $collectorIds
     * @param mixed $user
     * @return void
     */
    protected function pushToQueue(QueueInterface $queue, array $collectorIds, mixed $user): void
    {
        $job = new Job(
            name: CollectTranslationsJobHandler::class,
            payload: [
                'collector_ids' => $collectorIds,
                'user_id' => $user instanceof UserInterface ? $user->id() : null,
            ],
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
        yield new Field\Html(name: $this->fieldName('info'))
            ->group(trans('Collect'))
            ->content(html: new Message(
                text: trans('Missing translations will be created. Existing translations may be updated if unchanged, while user-edited translations are skipped.'),
                info: true,
                attributes: ['class' => 'mt-s mb-m'],
            ));
        
        $collectors = $action->container()->get(CollectorsInterface::class);
        $collectOptions = [];
        
        foreach($collectors as $collector) {
            $collectOptions[$collector->id()] = $collector->name();
        }
        
        yield new Field\Checkboxes(
            name: $this->fieldName('collector_ids'),
            label: trans('Translations to Collect')
        )
            ->group(trans('Collect'))
            ->options($collectOptions)
            ->validate('required|minItems:1');
    }
}