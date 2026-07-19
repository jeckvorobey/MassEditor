<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';

$request_service_file = __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginOperationRequestService.class.php';
$controller_file = __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/actions/shopMasseditorPluginBackendApply.controller.php';
if (is_file($request_service_file)) {
    require_once $request_service_file;
}
if (is_file($controller_file)) {
    require_once $controller_file;
}

class BackendApplyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        waRequest::reset();
        waLog::reset();
        $GLOBALS['fake_wa_system'] = new FakeWaSystem();
        $GLOBALS['fake_wa_system']->plugins['masseditor'] = new shopMasseditorPlugin(array(
            'operation_limit' => 100,
            'interface_language' => 'ru_RU',
        ));
    }

    public function testBackendApplyControllerAndSharedRequestReaderAreAvailable(): void
    {
        $this->assertTrue(class_exists('shopMasseditorPluginOperationRequestService'));
        $this->assertTrue(class_exists('shopMasseditorPluginBackendApplyController'));
    }

    public function testSharedRequestReaderKeepsTheExistingTypedPayloadContract(): void
    {
        $this->requireBackendApplyClasses();
        waRequest::$post = array(
            'product_ids' => array('11', '12'),
            'operation' => 'features',
            'feature_id' => '8',
            'feature_value_ids' => array('71', '72'),
            'filters' => array('query' => 'test'),
            'confirm_apply' => '1',
        );

        $payload = (new shopMasseditorPluginOperationRequestService())->readPost();

        $this->assertSame(array('11', '12'), $payload['product_ids']);
        $this->assertSame('features', $payload['operation']);
        $this->assertSame(8, $payload['feature_id']);
        $this->assertSame(array('71', '72'), $payload['feature_value_ids']);
        $this->assertSame(array('query' => 'test'), $payload['filters']);
        $this->assertSame(1, $payload['confirm_apply']);
    }

    public function testSuccessfulApplyReturnsLocalizedResultAndReloadFlag(): void
    {
        $this->requireBackendApplyClasses();
        $controller = new StubbedBackendApplyController();
        $controller->operationService = new SuccessfulBackendApplyServiceStub();
        $this->seedConfirmedPost();

        $controller->execute();

        $this->assertSame('Изменение цены · 2 товара · Операция выполнена.', $controller->response['message']);
        $this->assertTrue($controller->response['reload']);
        $this->assertTrue($controller->response['reset_selection']);
        $this->assertSame(array(), $controller->errors);
    }

    public function testValidationErrorIsReturnedWithoutTechnicalDetails(): void
    {
        $this->requireBackendApplyClasses();
        $controller = new StubbedBackendApplyController();
        $controller->operationService = new InvalidBackendApplyServiceStub();
        $this->seedConfirmedPost();

        $controller->execute();

        $this->assertSame(array('Проверьте значение операции.'), $controller->errors);
        $this->assertSame(array(), $controller->response);
    }

    public function testUnexpectedErrorIsLoggedAndHidden(): void
    {
        $this->requireBackendApplyClasses();
        $controller = new StubbedBackendApplyController();
        $controller->operationService = new UnexpectedBackendApplyServiceStub();
        $this->seedConfirmedPost();

        $controller->execute();

        $this->assertSame(array('Операцию не удалось выполнить. Повторите действие или проверьте журнал ошибок.'), $controller->errors);
        $this->assertStringNotContainsString('SQLSTATE', $controller->errors[0]);
        $this->assertCount(1, waLog::$logs);
        $this->assertStringContainsString('SQLSTATE', waLog::$logs[0]['message']);
        $this->assertSame('shop/plugins/masseditor.log', waLog::$logs[0]['file']);
    }

    public function testControllerRejectsNonAdminBeforeApply(): void
    {
        $this->requireBackendApplyClasses();
        $GLOBALS['fake_wa_system']->user = new FakeUser(9, false);
        $controller = new StubbedBackendApplyController();
        $controller->operationService = new SuccessfulBackendApplyServiceStub();
        $this->seedConfirmedPost();

        $controller->execute();

        $this->assertSame(array('Недостаточно прав для управления массовым редактором.'), $controller->errors);
        $this->assertSame(array(), $controller->response);
        $this->assertSame(0, $controller->operationService->applyCalls);
    }

    private function seedConfirmedPost(): void
    {
        waRequest::$method = 'post';
        waRequest::$post = array(
            'product_ids' => array(11, 12),
            'operation' => 'price',
            'mode' => 'set',
            'numeric_value' => '25',
            'confirm_apply' => 1,
        );
    }

    private function requireBackendApplyClasses(): void
    {
        $this->assertTrue(class_exists('shopMasseditorPluginOperationRequestService'));
        $this->assertTrue(class_exists('shopMasseditorPluginBackendApplyController'));
    }
}

if (class_exists('shopMasseditorPluginBackendApplyController')) {
    class StubbedBackendApplyController extends shopMasseditorPluginBackendApplyController
    {
        public $operationService;

        protected function createOperationService($operation_limit, $language)
        {
            return $this->operationService;
        }
    }
}

class SuccessfulBackendApplyServiceStub extends shopMasseditorPluginMassOperationService
{
    public $applyCalls = 0;

    public function apply(array $raw_request)
    {
        $this->applyCalls++;

        return array(
            'request' => $raw_request,
            'summary' => 'Изменение цены · 2 товара',
            'message' => 'Операция выполнена.',
            'skipped' => 0,
        );
    }
}

class InvalidBackendApplyServiceStub extends SuccessfulBackendApplyServiceStub
{
    public function apply(array $raw_request)
    {
        throw new InvalidArgumentException('Проверьте значение операции.');
    }
}

class UnexpectedBackendApplyServiceStub extends SuccessfulBackendApplyServiceStub
{
    public function apply(array $raw_request)
    {
        throw new RuntimeException('SQLSTATE[42000]: internal failure');
    }
}
