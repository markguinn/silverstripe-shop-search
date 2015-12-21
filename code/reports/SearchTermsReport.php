<?php
/**
 * Silverstripe report for searchs
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 09.24.2014
 * @package apluswhs.com
 * @subpackage
 */
class SearchTermsReport extends ShopPeriodReport
{
    protected $title = "Search Terms";
    protected $description = "Understand what users are searching for.";
    protected $dataClass = "SearchLog";
    protected $periodfield = "SearchLog.Created";

    public function columns()
    {
        return array(
            "Query" => array(
                "title" => "Query",
                "formatting" => '<a href=\"home/SearchForm?q=$ATT_val($Query)\" target=\"_new\">$Query</a>'
            ),
            'NumResults' => 'Results',
            'Quantity' => 'Searches',
            'MostRecent' => 'Most Recent',
        );
    }

    public function query($params)
    {
        $query = parent::query($params);
        $query->selectField($this->periodfield, "FilterPeriod")
            ->addSelect("SearchLog.Query")
            ->selectField("Count(SearchLog.ID)", "Quantity")
            ->selectField("Max(SearchLog.Created)", "MostRecent")
            ->selectField("Max(SearchLog.NumResults)", "NumResults");
        $query->addGroupby("SearchLog.Query");
        $query->addWhere("\"SearchLog\".\"Filters\" is null AND \"SearchLog\".\"ParentSearchID\" = '0'");
        $query->setOrderBy("Quantity", "DESC");
        return $query;
    }
}
