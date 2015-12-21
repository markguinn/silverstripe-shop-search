<?php
/**
 * Records search results for future suggestions and analysis
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 09.20.2013
 * @package shop_search
 */
class SearchLog extends DataObject
{
    private static $db = array(
        'Query'         => 'Varchar(255)',
        'Title'         => 'Varchar(255)', // title for breadcrumbs. any new facets added will be reflected here
        'Link'          => 'Varchar(255)',
        'Filters'       => 'Text', // json
        'NumResults'    => 'Int',
    );

    private static $has_one = array(
        'Member'        => 'Member',
        'ParentSearch'  => 'SearchLog', // used in constructing a search breadcrumb
    );


    /**
     * Generate the title if needed
     */
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->Title) {
            $this->Title = empty($this->Query) ? "Search" : "Search: {$this->Query}";
        }
    }


    /**
     * @return ArrayList
     */
    public function getBreadcrumbs()
    {
        $out    = new ArrayList();
        $cur    = $this;

        while ($cur && $cur->exists()) {
            $out->unshift($cur);
            $cur = $cur->ParentSearchID > 0 ? $cur->ParentSearch() : null;
        }

        return $out;
    }


    /**
     * @return array
     */
    public function getFiltersArray()
    {
        return $this->Filters ? json_decode($this->Filters, true) : array();
    }
}
