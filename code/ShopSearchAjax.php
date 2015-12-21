<?php
/**
 * Pending some changes introduced to the core shop module, this will supply
 * some standard, easily overridable ajax features.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 07.21.2014
 * @package shop_search
 */
class ShopSearchAjax extends Extension
{
    /**
     * @param SS_HTTPRequest $request
     * @param SS_HTTPResponse $response
     * @param ArrayData $results
     * @param array $data
     */
    public function updateSearchResultsResponse(&$request, &$response, $results, $data)
    {
        if ($request->isAjax() && $this->owner->hasExtension('AjaxControllerExtension')) {
            if (!$response) {
                $response = $this->owner->getAjaxResponse();
            }
            $response->addRenderContext('RESULTS', $results);
            $response->pushRegion('SearchResults', $results);
            $response->pushRegion('SearchHeader', $results);
            $response->triggerEvent('searchresults');
        }
    }
}
