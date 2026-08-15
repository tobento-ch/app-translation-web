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

class TranslationCrudControllerTest extends \Tobento\App\Crud\Testing\AbstractCrudTestCase
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
    
    public function testIndexAction()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
                
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());
        
        $this->getSeedFactory()->times(2)->create();
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Translations')
            ->assertCrudIndexHeaderColumnsExists(columns: ['status', 'message', 'translation', 'resource_locale', 'app_id', 'actions'])
            ->assertCrudIndexEntityCount(2);
    }
    
    public function testIndexActionInTableEditModeCanAutoTranslateIfEnabled()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
            new Feature\MachineTranslation(),
        ]);
        
        $this->withMachineTranslator();
        
        $http = $this->fakeHttp();
        $http->request(
            method: 'GET',
            uri: $this->generateIndexUri(),
            query: ['filter' => ['editable-columns' => ['translation']]],
        );
        
        $this->getSeedFactory(['resource_locale' => 'en'])->times(1)->create();
        $this->getSeedFactory(['resource_locale' => 'de'])->times(1)->create();

        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Auto-Translate')
            ->assertBodyContains(value: '"from_selector":"[data-entity-id=\"1\"] [data-field=\"message\"]"', escape: true)
            ->assertBodyContains(value: '"to_selector":"[data-entity-id=\"1\"] textarea[name=\"translation\"]"', escape: true)
            ->assertBodyContains(value: '"locale":"en"', escape: true)
            ->assertBodyContains(value: '"from_selector":"[data-entity-id=\"2\"] [data-field=\"message\"]"', escape: true)
            ->assertBodyContains(value: '"to_selector":"[data-entity-id=\"2\"] textarea[name=\"translation\"]"', escape: true)
            ->assertBodyContains(value: '"locale":"de"', escape: true)
            ->assertCrudIndexEntityCount(2);
    }
    
    public function testIndexActionInTableEditModeCantAutoTranslateIfDisabled()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(
            method: 'GET',
            uri: $this->generateIndexUri(),
            query: ['filter' => ['editable-columns' => ['translation']]],
        );
        
        $this->getSeedFactory()->times(2)->create();

        $http->response()
            ->assertStatus(200)
            ->assertBodyNotContains('Auto-Translate')
            ->assertCrudIndexEntityCount(2);
    }
    
    public function testIndexActionFailsWithoutPermission()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());

        $http->response()
            ->assertStatus(403)
            ->assertBodyContains('You don\'t have a required "translations" permission.');
    }
    
    public function testCreateActionIsDisabled()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateCreateUri());
        
        $http->response()->assertStatus(404);
    }
    
    public function testStoreActionIsDisabled()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: $this->generateStoreUri())->body([]);

        $http->response()->assertStatus(404);
    }
    
    public function testEditAction()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateEditUri(id: 1));
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
        ])->times(1)->create();
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Edit Translation')
            ->assertBodyNotContains('Auto-Translate')
            ->assertCrudFormFieldExists(field: 'status')
            ->assertCrudFormFieldExists(field: 'message')
            ->assertCrudFormFieldExists(field: 'translation')
            ->assertCrudFormFieldExists(field: 'translated_by')
            ->assertCrudFormFieldExists(field: 'origin_translation')
            ->assertCrudFormFieldExists(field: 'origin_translated_by')
            ->assertCrudFormFieldExists(field: 'user_id')
            ->assertCrudFormFieldExists(field: 'is_translation_missing')
            ->assertCrudFormFieldExists(field: 'notes')
            ->assertCrudFormFieldMissing(field: 'created_at')
            ->assertCrudFormFieldExists(field: 'app_id')
            ->assertCrudFormFieldExists(field: 'resource_name')
            ->assertCrudFormFieldExists(field: 'resource_locale')
            ->assertCrudFormFieldExists(field: 'resource_group')
            ->assertCrudFormFieldExists(field: 'resource_priority')
            ->assertCrudFormFieldExists(field: 'resource_filename');
    }

    public function testEditActionCanAutoTranslateIfEnabled()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
            new Feature\MachineTranslation(),
        ]);
        
        $this->withMachineTranslator();
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateEditUri(id: 1));
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'en',
        ])->times(1)->create();
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Edit Translation')
            ->assertCrudFormFieldExists(field: 'message')
            ->assertCrudFormFieldExists(field: 'translation')
            ->assertBodyContains('Auto-Translate')
            ->assertBodyContains(value: '"to":"translation"', escape: true)
            ->assertBodyContains(value: '"from":"message"', escape: true)
            ->assertBodyContains(value: '"locale":"en"', escape: true);
    }
    
    public function testEditActionFailsWithoutPermission()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateEditUri(id: 1));
        
        $this->getSeedFactory()->times(1)->create();
        
        $http->response()
            ->assertStatus(403)
            ->assertBodyContains('You don\'t have a required "translations.edit" permission.');
    }
    
    public function testUpdateAction()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'draft',
            'translation' => 'Willkommen',
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(1)->create();
        
        $http->response()
            ->assertStatus(302)
            ->assertLocation($this->generateIndexUri());
        
        $http->followRedirects()
            ->assertStatus(200);
        
        $entity = $this->getCrudRepository()->findById(1);
        $this->assertSame('Willkommen', $entity->translation());
        $this->assertSame('draft', $entity->status());
    }
    
    public function testUpdateActionFailsWithoutPermission()
    {
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'draft',
            'translation' => 'Willkommen',
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(1)->create();
        
        $http->response()
            ->assertStatus(403)
            ->assertBodyContains('You don\'t have a required "translations.edit" permission.');
    }
    
    public function testUpdateActionSetsIsModifiedWhenTranslationChanges()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'imported',
            'translation' => 'Willkommen',
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $entity = $this->getCrudRepository()->findById(1);
        $this->assertTrue($entity->isModified());
    }
    
    public function testUpdateActionSetsIsModifiedWhenStatusChanges()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'published',
            'translation' => 'Welcome',
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $entity = $this->getCrudRepository()->findById(1);
        $this->assertTrue($entity->isModified());
    }
    
    public function testUpdateActionSetsIsModifiedWhenMultipleFieldsChange()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'published',
            'translation' => 'Willkommen',
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $entity = $this->getCrudRepository()->findById(1);
        $this->assertTrue($entity->isModified());
    }
    
    public function testUpdateActionDoesNotSetIsModifiedWhenNothingChanges()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'imported',
            'translation' => 'Welcome',
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $entity = $this->getCrudRepository()->findById(1);
        $this->assertFalse($entity->isModified());
    }    
    
    public function testShowAction()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateShowUri(id: 1));
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'en',
        ])->times(1)->create();
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Translation Details')
            ->assertCrudFormFieldExists(field: 'status')
            ->assertCrudFormFieldExists(field: 'message')
            ->assertCrudFormFieldExists(field: 'translation')
            ->assertCrudFormFieldExists(field: 'translated_by')
            ->assertCrudFormFieldExists(field: 'origin_translation')
            ->assertCrudFormFieldExists(field: 'origin_translated_by')
            ->assertCrudFormFieldExists(field: 'user_id')
            ->assertCrudFormFieldExists(field: 'is_translation_missing')
            ->assertCrudFormFieldExists(field: 'notes')
            ->assertCrudFormFieldExists(field: 'created_at')
            ->assertCrudFormFieldExists(field: 'app_id')
            ->assertCrudFormFieldExists(field: 'resource_name')
            ->assertCrudFormFieldExists(field: 'resource_locale')
            ->assertCrudFormFieldExists(field: 'resource_group')
            ->assertCrudFormFieldExists(field: 'resource_priority')
            ->assertCrudFormFieldExists(field: 'resource_filename');
    }
    
    public function testShowActionFailsWithoutPermission()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateShowUri(id: 1));
        
        $this->getSeedFactory()->times(1)->create();
        
        $http->response()
            ->assertStatus(403)
            ->assertBodyContains('You don\'t have a required "translations" permission.');
    }
    
    public function testCopyActionIsDisabled()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateCopyUri(id: 1));
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'en',
        ])->times(1)->create();
        
        $http->response()->assertStatus(404);
    }
    
    public function testDeleteAction()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'DELETE', uri: $this->generateDeleteUri(id: 1));

        $this->getSeedFactory()->times(2)->create();

        $http->response()
            ->assertStatus(302)
            ->assertLocation($this->generateIndexUri());

        $http->followRedirects()
            ->assertStatus(200)
            ->assertCrudIndexEntityCount(1);

        $this->assertSame(1, $this->getCrudRepository()->count());
    }
    
    public function testDeleteActionFailsWithoutPermission()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'DELETE', uri: $this->generateDeleteUri(id: 1));

        $this->getSeedFactory()->times(2)->create();

        $http->response()->assertStatus(403);

        $this->assertSame(2, $this->getCrudRepository()->count());
    }
    
    public function testBulkEditActionStatus()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: $this->generateBulkUri(action: 'edit-status'))->body([
            'ids' => [1, 2],
            'status' => 'review',
            'translation' => 'Willkommen',
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(3)->create();
        
        $http->response()
            ->assertStatus(302)
            ->assertLocation($this->generateIndexUri());

        $http->followRedirects()
            ->assertStatus(200)
            ->assertCrudIndexEntityCount(3);

        $this->assertSame('review', $this->getCrudRepository()->findById(1)->status());
        $this->assertSame('review', $this->getCrudRepository()->findById(2)->status());
        $this->assertSame('imported', $this->getCrudRepository()->findById(3)->status());
    }
    
    public function testBulkEditActionStatusFailsWithoutPermission()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: $this->generateBulkUri(action: 'edit-status'))->body([
            'ids' => [1, 2],
            'status' => 'review',
            'translation' => 'Willkommen',
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
        ])->times(3)->create();
        
        $http->response()
            ->assertStatus(403)
            ->assertBodyContains('You don\'t have a required "translations.edit|translations.delete" permission.');

        $this->assertSame('imported', $this->getCrudRepository()->findById(1)->status());
        $this->assertSame('imported', $this->getCrudRepository()->findById(2)->status());
        $this->assertSame('imported', $this->getCrudRepository()->findById(3)->status());
    }
    
    public function testBulkDeleteAction()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: $this->generateBulkUri(action: 'bulk-delete'))->body([
            'ids' => [1, 2],
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
        ])->times(3)->create();
        
        $http->response()
            ->assertStatus(302)
            ->assertLocation($this->generateIndexUri());

        $http->followRedirects()
            ->assertStatus(200)
            ->assertCrudIndexEntityCount(1);

        $this->assertNull($this->getCrudRepository()->findById(1));
        $this->assertNull($this->getCrudRepository()->findById(2));
        $this->assertNotNull($this->getCrudRepository()->findById(3));
    }
    
    public function testBulkDeleteActionFailsWithoutPermission()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: $this->generateBulkUri(action: 'bulk-delete'))->body([
            'ids' => [1, 2],
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
        ])->times(3)->create();
        
        $http->response()
            ->assertStatus(403)
            ->assertBodyContains('You don\'t have a required "translations.edit|translations.delete" permission.');

        $this->assertNotNull($this->getCrudRepository()->findById(1));
        $this->assertNotNull($this->getCrudRepository()->findById(2));
        $this->assertNotNull($this->getCrudRepository()->findById(3));
    }
    
    public function testImportExportActionsAvailable()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false),
        ]);

        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());

        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
        ])->times(1)->create();

        $http->response()
            ->assertStatus(200)
            ->assertCrudIndexBulkActionsExists(actions: ['import', 'export']);
    }
    
    public function testImportExportActionsNotAvailableWithoutPermission()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());

        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
        ])->times(1)->create();

        $app = $this->bootingApp();
        $app->get(\Tobento\Service\Acl\AclInterface::class)->addPermissions([
            'translations',
        ]);
        
        $http->response()
            ->assertStatus(200)
            ->assertCrudIndexBulkActionsMissing(actions: ['import', 'export']);
    }
}