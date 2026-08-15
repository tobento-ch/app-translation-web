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

namespace Tobento\App\Translation\Web\Test\Feature\Controller;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Feature;

class TranslationCrudControllerPublishTest extends \Tobento\App\Crud\Testing\AbstractCrudTestCase
{
    use \Tobento\App\Testing\Database\RefreshDatabases;
    use \Tobento\App\Translation\Web\Test\Addon\MachineTranslatorAddon;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }
    
    protected function getCrudController(): string
    {
        return \Tobento\App\Translation\Web\Controller\TranslationCrudController::class;
    }
    
    public function testIndexActionDoesntShowsEditButtonWithoutPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations']);
        
        $this->getSeedFactory(['status' => 'published'])->times(1)->create();
        
        $http->response()
            ->assertStatus(200)
            ->assertCrudIndexEntityCount(1)
            ->assertCrudIndexButtonsMissing(buttons: ['edit'], group: 'entity');
    }
    
    public function testIndexActionShowsEditButtonWithPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.publish']);
        
        $this->getSeedFactory(['status' => 'published'])->times(1)->create();
        
        $http->response()
            ->assertStatus(200)
            ->assertCrudIndexEntityCount(1)
            ->assertCrudIndexButtonsExists(buttons: ['edit'], group: 'entity');
    }
    
    public function testEditPublishedFailsWithoutPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateEditUri(id: 1));
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations.edit']);
        
        $this->getSeedFactory(['status' => 'published'])->times(1)->create();
        
        $http->response()
            ->assertStatus(403)
            ->assertBodyContains('You can only edit or delete resources that are in Imported, Draft or Review status.');
    }
    
    public function testEditPublishedSucceedsWithPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateEditUri(id: 1));
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations.edit', 'translations.publish']);
        
        $this->getSeedFactory(['status' => 'published'])->times(1)->create();
        
        $http->response()->assertStatus(200);
    }
    
    public function testEditDraftSucceedsWithoutPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateEditUri(id: 1));
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations.edit']);
        
        $this->getSeedFactory(['status' => 'draft'])->times(1)->create();
        
        $http->response()->assertStatus(200);
    }

    public function testUpdatePublishedFailsWithoutPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'draft',
            'translation' => 'Willkommen',
        ]);
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit']);
        
        $this->getSeedFactory([
            'status' => 'published',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(1)->create();
        
        $http->followRedirects()
            ->assertStatus(403)
            ->assertBodyContains('You can only edit or delete resources that are in Imported, Draft or Review status.');
        
        $entity = $this->getCrudRepository()->findById(1);
        $this->assertSame('Welcome', $entity->translation());
        $this->assertSame('published', $entity->status());
    }
    
    public function testUpdateDraftToPublishedFailsWithoutPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'published',
            'translation' => 'Willkommen',
        ]);
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit']);
        
        $this->getSeedFactory([
            'status' => 'draft',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(1)->create();
        
        $http->followRedirects()
            ->assertStatus(403)
            ->assertBodyContains('You can only edit or delete resources that are in Imported, Draft or Review status.');
        
        $entity = $this->getCrudRepository()->findById(1);
        $this->assertSame('Welcome', $entity->translation());
        $this->assertSame('draft', $entity->status());
    }
    
    public function testUpdatePublishedSucceedsWithPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'draft',
            'translation' => 'Willkommen',
        ]);
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.publish']);
        
        $this->getSeedFactory([
            'status' => 'published',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $entity = $this->getCrudRepository()->findById(1);
        $this->assertSame('Willkommen', $entity->translation());
        $this->assertSame('draft', $entity->status());
    }
    
    public function testUpdateDraftSucceedsWithoutPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'review',
            'translation' => 'Willkommen',
        ]);
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit']);
        
        $this->getSeedFactory(['status' => 'draft'])->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $this->assertSame('review', $this->getCrudRepository()->findById(1)->status());
    }
    
    public function testDeletePublishedFailsWithoutPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'DELETE', uri: $this->generateDeleteUri(id: 1));

        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.delete']);
        
        $this->getSeedFactory(['status' => 'published'])->times(1)->create();
        
        $http->response()->assertStatus(403);

        $this->assertSame(1, $this->getCrudRepository()->count());
    }
    
    public function testDeletePublishedSucceedsWithPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'DELETE', uri: $this->generateDeleteUri(id: 1));

        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.delete', 'translations.publish']);
        
        $this->getSeedFactory(['status' => 'published'])->times(2)->create();
        
        $http->followRedirects()->assertStatus(200)->assertCrudIndexEntityCount(1);

        $this->assertSame(1, $this->getCrudRepository()->count());
    }
    
    public function testDeleteDraftSucceedsWithoutPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'DELETE', uri: $this->generateDeleteUri(id: 2));

        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.delete']);
        
        $this->getSeedFactory(['status' => 'draft'])->times(2)->create();
        
        $http->followRedirects()->assertStatus(200)->assertCrudIndexEntityCount(1);
        
        $this->assertSame(1, $this->getCrudRepository()->count());
    }
    
    public function testBulkEditPublishedStatusFailsWithoutPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: $this->generateBulkUri(action: 'edit-status'))->body([
            'ids' => [1, 2, 3],
            'status' => 'review',
            'translation' => 'Willkommen',
        ]);

        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete']);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(2)->create();
        
        $this->getSeedFactory([
            'status' => 'published',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(1)->create();
        
        $http->response()
            ->assertStatus(302)
            ->assertLocation($this->generateIndexUri());

        $http->followRedirects()
            ->assertStatus(200)
            ->assertCrudIndexEntityCount(3);

        $this->assertSame('review', $this->getCrudRepository()->findById(1)->status());
        $this->assertSame('review', $this->getCrudRepository()->findById(2)->status());
        $this->assertSame('published', $this->getCrudRepository()->findById(3)->status());
    }
    
    public function testBulkEditPublishedStatusSucceedsWithPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: $this->generateBulkUri(action: 'edit-status'))->body([
            'ids' => [1, 2, 3],
            'status' => 'review',
            'translation' => 'Willkommen',
        ]);

        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.publish']);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(2)->create();
        
        $this->getSeedFactory([
            'status' => 'published',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(1)->create();
        
        $http->response()
            ->assertStatus(302)
            ->assertLocation($this->generateIndexUri());

        $http->followRedirects()
            ->assertStatus(200)
            ->assertCrudIndexEntityCount(3);

        $this->assertSame('review', $this->getCrudRepository()->findById(1)->status());
        $this->assertSame('review', $this->getCrudRepository()->findById(2)->status());
        $this->assertSame('review', $this->getCrudRepository()->findById(3)->status());
    }
    
    public function testBulkDeletePublishedFailsWithoutPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: $this->generateBulkUri(action: 'bulk-delete'))->body([
            'ids' => [1, 2, 3],
        ]);

        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete']);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
        ])->times(2)->create();
        
        $this->getSeedFactory([
            'status' => 'published',
            'message' => 'Welcome',
        ])->times(1)->create();
        
        $http->response()
            ->assertStatus(302)
            ->assertLocation($this->generateIndexUri());

        $http->followRedirects()
            ->assertStatus(200)
            ->assertCrudIndexEntityCount(1);

        $this->assertNull($this->getCrudRepository()->findById(1));
        $this->assertNull($this->getCrudRepository()->findById(2));
        $this->assertSame('published', $this->getCrudRepository()->findById(3)->status());
    }
    
    public function testBulkDeletePublishedSucceedsWithPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: $this->generateBulkUri(action: 'bulk-delete'))->body([
            'ids' => [1, 2, 3],
        ]);

        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.publish']);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
        ])->times(2)->create();
        
        $this->getSeedFactory([
            'status' => 'published',
            'message' => 'Welcome',
        ])->times(1)->create();
        
        $http->response()
            ->assertStatus(302)
            ->assertLocation($this->generateIndexUri());

        $http->followRedirects()
            ->assertStatus(200)
            ->assertCrudIndexEntityCount(0);

        $this->assertNull($this->getCrudRepository()->findById(1));
        $this->assertNull($this->getCrudRepository()->findById(2));
        $this->assertNull($this->getCrudRepository()->findById(3));
    }
    
    public function testStatusOptionsForDraftWithoutPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateEditUri(id: 2));
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations.edit']);
        
        $this->getSeedFactory(['status' => 'draft'])->times(2)->create();
        
        $http->response()
            ->assertBodyContains('Imported')
            ->assertBodyContains('Draft')
            ->assertBodyContains('Pending Review')
            ->assertBodyNotContains('Published');
    }
    
    public function testStatusOptionsWithPublishPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateEditUri(id: 2));
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations.edit', 'translations.publish']);
        
        $this->getSeedFactory(['status' => 'draft'])->times(2)->create();
        
        $http->response()
            ->assertBodyContains('Imported')
            ->assertBodyContains('Draft')
            ->assertBodyContains('Pending Review')
            ->assertBodyContains('Published');
    }
}