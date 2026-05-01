<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';

class ProductSelectionServiceTest extends TestCase
{
    use InvokesPrivateMethods;

    public function testGetByIdsReturnsEmptyForNoIds(): void
    {
        $model = new shopMasseditorPluginProductModel();
        $service = new shopMasseditorPluginProductSelectionService($model);

        $this->assertSame(array(), $service->getByIds(array()));
    }

    public function testGetByIdsQueriesAndKeysById(): void
    {
        $model = new shopMasseditorPluginProductModel();
        $model->queueResponse('FROM shop_product p', new FakeQueryResult(array(
            array('id' => 5, 'name' => 'A'),
            array('id' => 9, 'name' => 'B'),
        )));

        $service = new shopMasseditorPluginProductSelectionService($model);
        $result = $service->getByIds(array(5, 9));

        $this->assertArrayHasKey(5, $result);
        $this->assertArrayHasKey(9, $result);
    }

    public function testGetPageNormalizesFiltersAndPagination(): void
    {
        $model = new shopMasseditorPluginProductModel();
        $model->queueResponse('SELECT COUNT(DISTINCT p.id)', new FakeQueryResult(array(), 51));
        $model->queueResponse('SELECT p.id, p.name, p.status', new FakeQueryResult(array(
            array('id' => 10, 'name' => 'One'),
        )));

        $service = new shopMasseditorPluginProductSelectionService($model);
        $result = $service->getPage(array(
            'query' => ' ABC ',
            'status' => 'broken',
            'availability' => 'available',
            'category_id' => 7,
            'page' => 99,
        ), 50);

        $this->assertSame('ABC', $result['filters']['query']);
        $this->assertSame('all', $result['filters']['status']);
        $this->assertSame('available', $result['filters']['availability']);
        $this->assertSame(7, $result['filters']['category_id']);
        $this->assertSame(2, $result['pagination']['page']);
        $this->assertSame(2, $result['pagination']['pages']);
    }

    public function testBuildConditionsCoversStatusAvailabilityAndCategory(): void
    {
        $service = new shopMasseditorPluginProductSelectionService(new shopMasseditorPluginProductModel());

        $conditions = $this->invokePrivate($service, 'buildConditions', array(array(
            'query' => 'sku',
            'status' => 'published',
            'availability' => 'unavailable',
            'category_id' => 3,
        )));

        $this->assertStringContainsString('LEFT JOIN shop_product_skus s', $conditions['joins']);
        $this->assertStringContainsString('INNER JOIN shop_category_products cpf', $conditions['joins']);
        $this->assertStringContainsString('p.status = i:status', $conditions['sql']);
        $this->assertStringContainsString('NOT EXISTS', $conditions['sql']);
        $this->assertSame('%sku%', $conditions['params']['query']);
        $this->assertSame(1, $conditions['params']['status']);
        $this->assertSame(3, $conditions['params']['category_id']);
    }

    public function testGetCategoriesReadsOrderedList(): void
    {
        $model = new shopMasseditorPluginProductModel();
        $model->queueResponse('FROM shop_category', new FakeQueryResult(array(
            array('id' => 2, 'name' => 'Cat B'),
        )));
        $service = new shopMasseditorPluginProductSelectionService($model);

        $this->assertCount(1, $service->getCategories());
    }

    public function testNormalizePageSizeUsesBounds(): void
    {
        $service = new shopMasseditorPluginProductSelectionService(new shopMasseditorPluginProductModel());

        $this->assertSame(50, $this->invokePrivate($service, 'normalizePageSize', array(0)));
        $this->assertSame(200, $this->invokePrivate($service, 'normalizePageSize', array(500)));
    }
}
