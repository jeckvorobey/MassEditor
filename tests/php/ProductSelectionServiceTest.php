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

    public function testGetPageLoadsWarehouseStockDetailsInBatch(): void
    {
        $model = new shopMasseditorPluginProductModel();
        $model->queueResponse('SELECT COUNT(DISTINCT p.id)', new FakeQueryResult(array(), 2));
        $model->queueResponse('SELECT p.id, p.name, p.status', new FakeQueryResult(array(
            array('id' => 10, 'name' => 'One', 'count' => null),
            array('id' => 11, 'name' => 'Two', 'count' => '1.5000'),
        )));
        $model->queueResponse('FROM shop_stock', new FakeQueryResult(array(
            array('id' => 3, 'name' => 'Main'),
            array('id' => 4, 'name' => 'Reserve'),
        )));
        $model->queueResponse('FROM shop_product_skus sku', new FakeQueryResult(array(
            array('product_id' => 10, 'stock_id' => 3, 'count' => '5.0000'),
            array('product_id' => 10, 'stock_id' => 4, 'count' => null),
            array('product_id' => 11, 'stock_id' => 3, 'count' => '1.5000'),
        )));

        $service = new shopMasseditorPluginProductSelectionService($model);
        $result = $service->getPage(array(), 50);

        $this->assertSame(array(
            array('stock_id' => 3, 'stock_name' => 'Main', 'count' => 5.0, 'count_view' => '5'),
            array('stock_id' => 4, 'stock_name' => 'Reserve', 'count' => null, 'count_view' => '∞'),
        ), $result['products'][0]['stock_details']);
        $this->assertNull($result['products'][0]['count']);
        $this->assertSame(array(
            array('stock_id' => 3, 'stock_name' => 'Main', 'count' => 1.5, 'count_view' => '1.5'),
            array('stock_id' => 4, 'stock_name' => 'Reserve', 'count' => 0.0, 'count_view' => '0'),
        ), $result['products'][1]['stock_details']);
        $this->assertSame('1.5000', $result['products'][1]['count']);
        $this->assertStringContainsString('sku.product_id IN', $model->queries[3]['sql']);
        $this->assertStringContainsString('CASE', $model->queries[3]['sql']);
    }

    public function testGetPageKeepsProductCountAndTreatsAnyNullWarehouseSkuAsInfinite(): void
    {
        $model = new shopMasseditorPluginProductModel();
        $model->queueResponse('SELECT COUNT(DISTINCT p.id)', new FakeQueryResult(array(), 1));
        $model->queueResponse('SELECT p.id, p.name, p.status', new FakeQueryResult(array(
            array('id' => 10, 'name' => 'One', 'count' => '12.0000'),
        )));
        $model->queueResponse('FROM shop_stock', new FakeQueryResult(array(
            array('id' => 3, 'name' => 'Main'),
            array('id' => 4, 'name' => 'Reserve'),
        )));
        $model->queueResponse('FROM shop_product_skus sku', new FakeQueryResult(array(
            array('product_id' => 10, 'stock_id' => 3, 'count' => '5.0000', 'has_infinite' => 1),
            array('product_id' => 10, 'stock_id' => 4, 'count' => '7.0000', 'has_infinite' => 0),
        )));

        $service = new shopMasseditorPluginProductSelectionService($model);
        $result = $service->getPage(array(), 50);

        $this->assertSame('12.0000', $result['products'][0]['count']);
        $this->assertSame(array(
            array('stock_id' => 3, 'stock_name' => 'Main', 'count' => null, 'count_view' => '∞'),
            array('stock_id' => 4, 'stock_name' => 'Reserve', 'count' => 7.0, 'count_view' => '7'),
        ), $result['products'][0]['stock_details']);
    }

    public function testBuildConditionsCoversExtendedSearchStatusAvailabilityAndCategory(): void
    {
        $service = new shopMasseditorPluginProductSelectionService(new shopMasseditorPluginProductModel());

        $conditions = $this->invokePrivate($service, 'buildConditions', array(array(
            'query' => 'sku',
            'status' => 'published',
            'availability' => 'unavailable',
            'category_id' => 3,
        )));

        $this->assertStringContainsString('LEFT JOIN shop_product_skus s', $conditions['joins']);
        $this->assertStringContainsString('LEFT JOIN shop_product_tags pt_search', $conditions['joins']);
        $this->assertStringContainsString('LEFT JOIN shop_tag t_search', $conditions['joins']);
        $this->assertStringContainsString('LEFT JOIN shop_category_products cp_search', $conditions['joins']);
        $this->assertStringContainsString('LEFT JOIN shop_category c_search', $conditions['joins']);
        $this->assertStringContainsString('INNER JOIN shop_category_products cpf', $conditions['joins']);
        $this->assertStringContainsString('p.name LIKE s:query', $conditions['sql']);
        $this->assertStringContainsString('p.url LIKE s:query', $conditions['sql']);
        $this->assertStringContainsString('p.summary LIKE s:query', $conditions['sql']);
        $this->assertStringContainsString('p.description LIKE s:query', $conditions['sql']);
        $this->assertStringContainsString('s.sku LIKE s:query', $conditions['sql']);
        $this->assertStringContainsString('t_search.name LIKE s:query', $conditions['sql']);
        $this->assertStringContainsString('c_search.name LIKE s:query', $conditions['sql']);
        $this->assertStringContainsString('p.status = i:status', $conditions['sql']);
        $this->assertStringContainsString('NOT EXISTS', $conditions['sql']);
        $this->assertSame('%sku%', $conditions['params']['query']);
        $this->assertSame(1, $conditions['params']['status']);
        $this->assertSame(3, $conditions['params']['category_id']);
    }

    public function testGetIdsByFiltersUsesExtendedConditions(): void
    {
        $model = new shopMasseditorPluginProductModel();
        $model->queueResponse('SELECT DISTINCT p.id', new FakeQueryResult(array(
            array('id' => 3),
            array('id' => 7),
        )));
        $service = new shopMasseditorPluginProductSelectionService($model);

        $this->assertSame(array(3, 7), $service->getIdsByFilters(array('query' => 'summer'), 50));

        $this->assertStringContainsString('p.summary LIKE s:query', $model->queries[0]['sql']);
        $this->assertStringContainsString('t_search.name LIKE s:query', $model->queries[0]['sql']);
        $this->assertStringContainsString('c_search.name LIKE s:query', $model->queries[0]['sql']);
        $this->assertSame('%summer%', $model->queries[0]['params']['query']);
    }

    public function testGetSearchSuggestionsAppliesActiveFiltersAndReturnsUniqueLimitedValues(): void
    {
        $model = new shopMasseditorPluginProductModel();
        $model->queueResponse('SELECT DISTINCT p.id, p.name', new FakeQueryResult(array(
            array(
                'id' => 1,
                'name' => 'Summer dress',
                'url' => 'summer-dress',
                'summary' => 'Light summer item',
                'description' => '<p>Long summer description</p>',
                'sku' => 'SUM-1',
                'tag_name' => 'Summer',
                'category_name' => 'Dresses',
            ),
            array(
                'id' => 2,
                'name' => 'Summer dress',
                'url' => 'other',
                'summary' => '',
                'description' => '',
                'sku' => 'SUM-2',
                'tag_name' => 'Sale',
                'category_name' => 'Summer',
            ),
        )));
        $service = new shopMasseditorPluginProductSelectionService($model);

        $suggestions = $service->getSearchSuggestions(array(
            'query' => 'ignored-current-query',
            'status' => 'published',
            'availability' => 'available',
            'category_id' => 9,
        ), ' sum ', 3);

        $this->assertSame(array('SUM-1', 'Summer dress', 'summer-dress'), $suggestions);
        $this->assertStringContainsString('p.status = i:status', $model->queries[0]['sql']);
        $this->assertStringContainsString('cpf.category_id = i:category_id', $model->queries[0]['sql']);
        $this->assertStringContainsString('EXISTS', $model->queries[0]['sql']);
        $this->assertStringNotContainsString('ignored-current-query', $model->queries[0]['sql']);
        $this->assertSame('%sum%', $model->queries[0]['params']['query']);
        $this->assertSame(1, $model->queries[0]['params']['status']);
        $this->assertSame(9, $model->queries[0]['params']['category_id']);
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
