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
use Tobento\App\Translation\Web\Collector\CollectorsInterface;
use Tobento\App\Translation\Web\Onboarding\OnboardingInterface;
use Tobento\App\Translation\Web\Queue\AutoTranslateJobHandler;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\App\User\UserInterface;
use Tobento\Service\Queue\Job;
use Tobento\Service\Queue\QueueInterface;
use Tobento\Service\Requester\RequesterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\View\ViewInterface;
use function Tobento\App\Translation\trans;

/**
 * Bulk action for auto‑translating translation entries.
 *
 * Translates missing or empty values for the selected or filtered entries
 * using the configured machine translator. Existing user‑edited values are
 * never overwritten, and no new keys are created. The work runs as a queued
 * job and requires the `translations.auto_translate` permission.
 */
class AutoTranslateBulkAction extends Action\AbstractAction implements Action\BulkActionInterface
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
        protected string $name = 'auto-translate',
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
        $this->modalButtonLabel(trans('Auto Translate'));
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
     * @param FilterProcessorInterface $filterProcessor
     * @param QueueInterface $queue
     * @param ResponserInterface $responser
     * @return void
     * @throws ActionProcessException
     * @psalm-suppress RedundantCondition
     * @psalm-suppress NoValue
     */
    public function processBulk(
        RequesterInterface $requester,
        FilterProcessorInterface $filterProcessor,
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

        $this->actionProcessor()->processFields(action: $storeAction, entity: new Entity());

        // Extract validated input
        $input = $storeAction->getInput();
        
        // Ids selection mode
        $selectionMode = $input->get($this->fieldName('selection_mode'), 'ids');
        if ($selectionMode === 'ids') {
            $where = [$this->controller()->entityIdName() => ['in' => $input->get('ids', [])]];
            $orderBy = [];
        } else {
            // filtered selection mode
            // Get Index action for filters
            $indexAction = $this->actions()->get('index');

            if (is_null($indexAction)) {
                throw new ActionNotFoundException(actionName: 'index');
            }

            $indexAction->setFields($this->controller()->getConfiguredFields(action: $indexAction));

            // Handle filters:
            $filters = $this->controller()->getConfiguredFilters($indexAction);
            $filters = $filterProcessor->processFilters(filters: $filters, action: $indexAction);
            
            $where = $filters->getWhereParameters();
            $orderBy = $filters->getOrderByParameters();
        }
        
        $user = $requester->request()->getAttribute(UserInterface::class);
        
        // Queue onboarding job
        $this->pushToQueue(
            queue: $queue,
            payload: [
                'user_id' => $user instanceof UserInterface ? $user->id() : null,
                'filters' => [
                    'where' => $where,
                    'orderBy' => $orderBy,
                ],
            ],
        );

        $responser->messages()->add(
            level: 'success',
            message: trans('Auto‑translation has started and will continue in the background.'),
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
            name: AutoTranslateJobHandler::class,
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
     * @psalm-suppress PossiblyUnusedParam
     */
    protected function configureFields(ActionInterface $action): iterable|FieldsInterface
    {
        yield new Field\Html(name: $this->fieldName('info'))
            ->group(trans('Auto Translate'))
            ->content(html: new Message(
                text: trans('Automatically fills missing or empty translations for the selected or filtered entries without overwriting existing values.'),
                info: true,
                attributes: ['class' => 'mt-s mb-m'],
            ));
        
        yield new Field\Select(
            name: $this->fieldName('selection_mode'),
            label: trans('Records to Auto Translate')
        )
            ->group(trans('Auto Translate'))
            ->options([
                'ids' => trans('Selected Records'),
                'filtered' => trans('All Filtered Records'),
            ]);
    }
}