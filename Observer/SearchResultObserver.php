<?php
/**
 * Copyright 2016-2018 Henrik Hedelund
 * Copyright 2020      Falco Nogatz
 *
 * This file is part of Chessio_Matomo.
 *
 * Chessio_Matomo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Chessio_Matomo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Chessio_Matomo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Chessio\Matomo\Observer;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ObserverInterface;

/**
 * Observer for `catalog_product_collection_load_after'
 *
 * @see http://developer.matomo.org/guides/tracking-javascript-guide#internal-search-tracking
 */
class SearchResultObserver implements ObserverInterface
{

    /**
     * Matomo tracker instance
     *
     * @var \Chessio\Matomo\Model\Tracker
     */
    protected $_matomoTracker;

    /**
     * Matomo data helper
     *
     * @var \Chessio\Matomo\Helper\Data $_dataHelper
     */
    protected $_dataHelper;

    /**
     * Search query factory
     *
     * @var \Magento\Search\Model\QueryFactory $_queryFactory
     */
    protected $_queryFactory;

    /**
     * Current view
     *
     * @var \Magento\Framework\App\ViewInterface $_view
     */
    protected $_view;

    /**
     * @var \Magento\Framework\App\Request\Http $request
     */
    private $request;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Request\Http $request
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $request
    ) {
        $this->request = $request;
    }

    /**
     * Push `trackSiteSearch' to tracker on search result page
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return \Chessio\Matomo\Observer\SearchResultObserver
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // Skip executes in case the current page isn't the search result page
        if ($this->request->getFullActionName() !== 'catalogsearch_result_index') {
            return $this;
        }

        if (!$this->getDataHelper()->isTrackingEnabled()) {
            return $this;
        }

        $query = $this->getQueryFactory()->get();
        $matomoBlock = $this->getView()->getLayout()->getBlock('matomo.tracker');
        /** @var \Magento\Search\Model\Query $query */
        /** @var \Chessio\Matomo\Block\Matomo $matomoBlock */

        $keyword = $query->getQueryText();
        $resultsCount = $query->getNumResults();

        if ($resultsCount === null) {
            // If this is a new search query the result count hasn't been saved
            // yet so we have to fetch it from the search result block instead.
            $resultBock = $this->getView()->getLayout()->getBlock('search.result');
            /** @var \Magento\CatalogSearch\Block\Result $resultBock */
            if ($resultBock) {
                $resultsCount = $resultBock->getResultCount();
            }
        }

        if ($resultsCount === null) {
            $this->getMatomoTracker()->trackSiteSearch($keyword);
        } else {
            $this->getMatomoTracker()->trackSiteSearch(
                $keyword,
                false,
                (int) $resultsCount
            );
        }

        if ($matomoBlock) {
            // Don't push `trackPageView' when `trackSiteSearch' is set
            $matomoBlock->setSkipTrackPageView(true);
        }

        return $this;
    }

    /**
     * It's a heavy object, so let's lazy load it
     *
     * @return \Chessio\Matomo\Model\Tracker
     */
    private function getMatomoTracker()
    {
        if (!$this->_matomoTracker) {
            $this->_matomoTracker = ObjectManager::getInstance()
                ->get(\Chessio\Matomo\Model\Tracker::class);
        }

        return $this->_matomoTracker;
    }

    /**
     * It's a heavy object, so let's lazy load it
     *
     * @return \Chessio\Matomo\Helper\Data
     */
    private function getDataHelper()
    {
        if (!$this->_dataHelper) {
            $this->_dataHelper = ObjectManager::getInstance()
                ->get(\Chessio\Matomo\Helper\Data::class);
        }

        return $this->_dataHelper;
    }

    /**
     *  It's a heavy object, so let's lazy load it
     *
     * @return \Magento\Search\Model\QueryFactory
     */
    private function getQueryFactory()
    {
        if (!$this->_queryFactory) {
            $this->_queryFactory = ObjectManager::getInstance()
                ->get(\Magento\Search\Model\QueryFactory::class);
        }

        return $this->_queryFactory;
    }

    /**
     * It's a heavy object, so let's lazy load it
     *
     * @return \Magento\Framework\App\ViewInterface
     */
    private function getView()
    {
        if (!$this->_view) {
            $this->_view = ObjectManager::getInstance()
                ->get(\Magento\Framework\App\ViewInterface::class);
        }

        return $this->_view;
    }
}
