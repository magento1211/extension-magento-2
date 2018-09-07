<?php

namespace Emartech\Emarsys\Api;

/**
 * Interface CategoriesApiInterface
 * @package Emartech\Emarsys\Api
 */
interface CategoriesApiInterface
{
    /**
     * @param int    $page
     * @param int    $pageSize
     * @param string $storeId
     *
     * @return \Emartech\Emarsys\Api\Data\CategoriesApiResponseInterface
     */
    public function get($page, $pageSize, $storeId);
}
