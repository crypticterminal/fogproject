<?php
/**
 * FOG Manager Controller, main object mass getter
 *
 * PHP version 5
 *
 * @category FOGManagerController
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * FOG Manager Controller, main object mass getter
 *
 * @category FOGManagerController
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class FOGManagerController extends FOGBase
{
    /**
     * The main class for the object
     *
     * @var string
     */
    protected $childClass;
    /**
     * The table name for the object
     *
     * @var string
     */
    protected $databaseTable;
    /**
     * The common names and fields
     *
     * @var array
     */
    protected $databaseFields = array();
    /**
     * The Flipped fields
     *
     * @var array
     */
    protected $databaseFieldsFlipped = array();
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array();
    /**
     * The Class relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array();
    /**
     * The additional fields
     *
     * @var array
     */
    protected $additionalFields = array();
    /**
     * The load template
     *
     * SELECT <field(s)> FROM `<table>` <join> <where>
     *
     * @var string
     */
    protected $loadQueryTemplate = 'SELECT %s FROM `%s` %s %s %s %s %s';
    /**
     * The load groupby template
     *
     * @var string
     */
    protected $loadQueryGroupTemplate = 'SELECT %s FROM (%s) `%s` %s %s %s %s %s';
    /**
     * The count template
     *
     * @var string
     */
    protected $countQueryTemplate = 'SELECT COUNT(`%s`.`%s`) AS `total` FROM `%s`%s LIMIT 1';
    /**
     * The update template
     *
     * @var string
     */
    protected $updateQueryTemplate = 'UPDATE `%s` SET %s %s';
    /**
     * The destroy template
     *
     * @var string
     */
    protected $destroyQueryTemplate = "DELETE FROM `%s` WHERE `%s`.`%s` IN (%s)";
    /**
     * The exists template
     *
     * @var string
     */
    protected $existsQueryTemplate = "SELECT COUNT(`%s`.`%s`) AS `total` FROM `%s` WHERE `%s`.`%s`=%s AND `%s`.`%s` <> %s";
    /**
     * The insert batch template
     *
     * @var string
     */
    protected $insertBatchTemplate = "INSERT INTO `%s` (`%s`) VALUES %s ON DUPLICATE KEY UPDATE %s";
    /**
     * Initializes the manager class
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->childClass = preg_replace(
            '#_?Manager$#',
            '',
            get_class($this)
        );
        $classVars = self::getClass(
            $this->childClass,
            '',
            true
        );
        $classGet = array(
            'databaseTable',
            'databaseFields',
            'additionalFields',
            'databaseFieldsRequired',
            'databaseFieldClassRelationships',
        );
        $this->databaseTable =& $classVars[$classGet[0]];
        $this->databaseFields =& $classVars[$classGet[1]];
        $this->additionalFields =& $classVars[$classGet[2]];
        $this->databaseFieldsRequired =& $classVars[$classGet[3]];
        $this->databaseFieldClassRelationships =& $classVars[$classGet[4]];
        $this->databaseFieldsFlipped = array_flip($this->databaseFields);
        unset($classGet);
    }
    /**
     * Finds items related to the main object
     *
     * @param array  $findWhere     what to find
     * @param string $whereOperator how to combine where items
     * @param string $orderBy       how to order fields
     * @param string $sort          how the sort order
     * @param string $compare       how to compare
     * @param string $groupBy       how to group fields
     * @param bool   $not           use not operator
     * @param mixed  $idField       what fields to get
     * @param bool   $onecompare    second where uses AND
     * @param string $filter        array function for filter
     *
     * @return array
     */
    public function find(
        $findWhere = array(),
        $whereOperator = 'AND',
        $orderBy = 'name',
        $sort = 'ASC',
        $compare = '=',
        $groupBy = false,
        $not = false,
        $idField = false,
        $onecompare = true,
        $filter = 'array_unique'
    ) {
        // Fail safe defaults
        if (empty($findWhere)) {
            $findWhere = array();
        }
        if (empty($whereOperator)) {
            $whereOperator = 'AND';
        }
        if (empty($sort)) {
            $sort = 'ASC';
        }
        $this->orderBy($orderBy);
        if (empty($compare)) {
            $compare = '=';
        }
        $not = (
            $not ?
            ' NOT ' :
            ' '
        );
        $whereArray = $whereArrayAnd =array();
        if (count($findWhere) > 0) {
            $count = 0;
            foreach ($findWhere as $field => $value) {
                $key = trim($field);
                if (is_array($value) && count($value) > 0) {
                    foreach ($value as $i => &$val) {
                        $val = trim($val);
                        // Define the key
                        $k = sprintf(
                            '%s_%d',
                            $key,
                            $i
                        );
                        // Define param keys
                        $findKeys[] = sprintf(
                            ':%s',
                            $k
                        );
                        // Define the param array
                        $findVals[$k] = $val;
                    }
                    $whereArray[] = sprintf(
                        '`%s`.`%s`%sIN (%s)',
                        $this->databaseTable,
                        $this->databaseFields[$field],
                        $not,
                        implode(',', $findKeys)
                    );
                } else {
                    if (is_array($value)) {
                        $value = '';
                    }
                    $value = trim($value);
                    $k = sprintf(
                        '%s',
                        $key
                    );
                    // Define the param keys
                    $findKeys[] = $findKey = sprintf(
                        ':%s',
                        $key
                    );
                    // Define the param array
                    $findVals[$k] = $value;
                    $whereArray[] = sprintf(
                        '`%s`.`%s`%s%s',
                        $this->databaseTable,
                        $this->databaseFields[$field],
                        (
                            preg_match('#%#', (string)$value) ?
                            sprintf(' %sLIKE ', $not) :
                            sprintf(
                                '%s%s',
                                (
                                    trim($not) ? '!' : ''
                                ),
                                (
                                    $onecompare ?
                                    (!$count ? $compare : '=') :
                                    $compare
                                )
                            )
                        ),
                        $findKey
                    );
                }
                $count++;
            }
        }
        if (!is_array($orderBy)) {
            $orderBy = sprintf(
                'ORDER BY %s`%s`.`%s`%s',
                ($orderBy == 'name' ? 'LOWER(' : ''),
                $this->databaseTable,
                $this->databaseFields[$orderBy],
                ($orderBy == 'name' ? ')' : '')
            );
            if ($groupBy) {
                $groupBy = sprintf(
                    'GROUP BY `%s`.`%s`',
                    $this->databaseTable,
                    $this->databaseFields[$groupBy]
                );
            } else {
                $groupBy = '';
            }
        } else {
            $orderBy = '';
        }
        list(
            $join,
            $whereArrayAnd
        ) = self::getClass($this->childClass)->buildQuery(
            $not,
            $compare
        );
        $knownEnable = array(
            'Image',
            'Snapin',
            'StorageNode'
        );
        $nonEnable = !(in_array($this->childClass, $knownEnable));
        $isEnabled = array_key_exists(
            'isEnabled',
            $this->databaseFields
        );
        if ($nonEnable && $isEnabled) {
            $isEnabled = sprintf(
                '`%s`=1',
                $this->databaseFields['isEnabled']
            );
        }
        if ($nonEnable && $isEnabled) {
            $findKeys[] = ':isEnabled';
            $findVals['isEnabled'] = 1;
            $isEnabled = sprintf(
                '`%s`=:isEnabled',
                $this->databaseFields['isEnabled']
            );
        }
        $idFields = array();
        foreach ((array)$idField as &$id) {
            $id = trim($id);
            $idFields[] = $this->databaseFields[$id];
            unset($id);
        }
        $idFields = array_filter($idFields);
        $idField = $idFields;
        unset($idFields);
        $query = sprintf(
            $this->loadQueryTemplate,
            (
                count($idField) > 0 ?
                sprintf('`%s`', implode('`,`', (array)$idField)) :
                '*'
            ),
            $this->databaseTable,
            $join,
            (
                count($whereArray) > 0 ?
                sprintf(
                    ' WHERE %s%s',
                    implode(" $whereOperator ", (array)$whereArray),
                    (
                        $isEnabled ?
                        sprintf(' AND %s', $isEnabled) :
                        ''
                    )
                ) :
                (
                    $isEnabled ?
                    sprintf(' WHERE %s', $isEnabled) :
                    ''
                )
            ),
            (
                count($whereArrayAnd) > 0 ?
                (
                    count($whereArray) > 0 ?
                    sprintf(
                        'AND %s',
                        implode(" $whereOperator ", (array)$whereArrayAnd)
                    ) :
                    sprintf(
                        ' WHERE %s',
                        implode(" $whereOperator ", (array)$whereArrayAnd)
                    )
                ) :
                ''
            ),
            $orderBy,
            $sort
        );
        if ($groupBy) {
            $query = sprintf(
                $this->loadQueryGroupTemplate,
                (
                    $idField ? sprintf('`%s`', implode('`,`', $idField)) : '*'
                ),
                $query,
                $this->databaseTable,
                $join,
                (
                    count($whereArray) > 0?
                    sprintf(
                        ' WHERE %s%s',
                        implode(" $whereOperator ", (array)$whereArray),
                        (
                            $isEnabled ?
                            sprintf(' AND %s', $isEnabled)
                            : ''
                        )
                    ) :
                    (
                        $isEnabled ?
                        sprintf(
                            ' WHERE %s',
                            $isEnabled
                        ) :
                        ''
                    )
                ),
                (
                    count($whereArrayAnd) > 0 ?
                    (
                        count($whereArray) > 0 ?
                        sprintf(
                            'AND %s',
                            implode(" $whereOperator ", (array)$whereArrayAnd)
                        ) :
                        sprintf(
                            ' WHERE %s',
                            implode(" $whereOperator ", (array)$whereArrayAnd)
                        )
                    ) :
                    ''
                ),
                $groupBy,
                $orderBy,
                $sort
            );
        }
        $data = array();
        self::$DB->query($query, array(), $findVals);
        if ($idField) {
            $data = (array)self::$DB
                ->fetch('', 'fetch_all')
                ->get($idField);
            if ($filter) {
                return @$filter($data);
            }
            if (!is_array($data)) {
                return $data;
            }
            if (count($data) === 1) {
                $data = array_shift($data);
            }
            if (empty($filter)) {
                return $data;
            }
        } else {
            $vals = self::$DB
                ->fetch('', 'fetch_all')
                ->get();
            foreach ((array)$vals as &$val) {
                $data[] = self::getClass($this->childClass)
                    ->setQuery($val);
                unset($val);
            }
        }
        $data = array_filter((array)$data);
        $data = array_values($data);
        if ($filter) {
            return @$filter($data);
        }
        return $data;
    }
    /**
     * Returns the count of items
     *
     * @param array  $findWhere     what to find and count
     * @param string $whereOperator how to scan for where multiples
     * @param string $compare       how to compare items
     *
     * @return int
     */
    public function count(
        $findWhere = array(),
        $whereOperator = 'AND',
        $compare = '='
    ) {
        if (empty($findWhere)) {
            $findWhere = array();
        }
        if (empty($whereOperator)) {
            $whereOperator = 'AND';
        }
        if (empty($compare)) {
            $compare = '=';
        }
        $whereArray = array();
        $countVals = $countKeys = array();
        if (count($findWhere)) {
            array_walk(
                $findWhere,
                function (
                    &$value,
                    &$field
                ) use (
                    &$whereArray,
                    $compare,
                    &$countVals,
                    &$countKeys
                ) {
                    $field = trim($field);
                    if (is_array($value)) {
                        foreach ((array)$value as $index => &$val) {
                            $countKeys[] = sprintf(':countVal%d', $index);
                            $countVals[sprintf('countVal%d', $index)] = $val;
                            unset($val);
                        }
                        $whereArray[] = sprintf(
                            "`%s`.`%s` IN (%s)",
                            $this->databaseTable,
                            $this->databaseFields[$field],
                            implode(',', $countKeys)
                        );
                    } else {
                        $countVals['countVal'] = $value;
                        $whereArray[] = sprintf(
                            '`%s`.`%s`%s:countVal',
                            $this->databaseTable,
                            $this->databaseFields[$field],
                            (
                                preg_match(
                                    '#%#',
                                    $value
                                ) ?
                                ' LIKE' :
                                $compare
                            )
                        );
                    }
                    unset($value, $field);
                }
            );
        }
        $knownEnable = array(
            'Image',
            'Snapin',
            'StorageNode'
        );
        $nonEnable = !(in_array($this->childClass, $knownEnable));
        $isEnabled = array_key_exists(
            'isEnabled',
            $this->databaseFields
        );
        if ($nonEnable && $isEnabled) {
            $isEnabled = sprintf(
                '`%s`=1',
                $this->databaseFields['isEnabled']
            );
        }
        $query = sprintf(
            $this->countQueryTemplate,
            $this->databaseTable,
            $this->databaseFields['id'],
            $this->databaseTable,
            (
                count($whereArray) ?
                sprintf(
                    ' WHERE %s%s',
                    implode(
                        sprintf(
                            ' %s ',
                            $whereOperator
                        ),
                        (array)$whereArray
                    ),
                    (
                        $isEnabled ?
                        sprintf(
                            ' AND %s',
                            $isEnabled
                        ) :
                        ''
                    )
                ) :
                (
                    $isEnabled ?
                    sprintf(
                        ' WHERE %s',
                        $isEnabled
                    ) :
                    ''
                )
            )
        );
        return (int)self::$DB
            ->query($query, array(), $countVals)
            ->fetch()
            ->get('total');
    }
    /**
     * Inserts data in mass to the database
     *
     * @param array $fields the fields to insert into.
     * @param array $values the values to insert.
     *
     * @return array
     */
    public function insertBatch($fields, $values)
    {
        $fieldlength = count($fields);
        $valuelength = count($values);
        if ($fieldlength < 1) {
            throw new Exception(_('No fields passed'));
        }
        if ($valuelength < 1) {
            throw new Exception(_('No values passed'));
        }
        $vals = array();
        $insertVals = array();
        foreach ((array)$fields as &$field) {
            $count = 0;
            foreach ((array)$values as &$value) {
                $insertKeys = array();
                foreach ((array)$value as &$val) {
                    $key = sprintf(
                        '%s_%d',
                        $field,
                        $count
                    );
                    $insertKeys[] = sprintf(
                        ':%s',
                        $key
                    );
                    $val = trim($val);
                    $insertVals[$key] = $val;
                    unset($val);
                    $count++;
                }
                $vals[] = sprintf('(%s)', implode(',', (array)$insertKeys));
                unset($value);
            }
        }
        if (count($vals) < 1) {
            throw new Exception(_('No data to insert'));
        }
        $keys = array();
        foreach ((array)$fields as &$key) {
            $key = $this->databaseFields[$key];
            $keys[] = $key;
            $dups[] = sprintf(
                '`%s`.`%s`=VALUES(`%s`.`%s`)',
                $this->databaseTable,
                $key,
                $this->databaseTable,
                $key
            );
            unset($key);
        }
        $query = sprintf(
            $this->insertBatchTemplate,
            $this->databaseTable,
            implode('`,`', $keys),
            implode(',', $vals),
            implode(',', $dups)
        );
        unset($vals, $keys, $dups);
        self::$DB->query($query, array(), $insertVals);
        return array(
            self::$DB->insertId(),
            self::$DB->affectedRows()
        );
    }
    /**
     * Function deals with enmass updating
     *
     * @param array  $findWhere     what specific to update
     * @param string $whereOperator what to join where with
     * @param array  $insertData    the data to update
     *
     * @return bool
     */
    public function update(
        $findWhere = array(),
        $whereOperator = 'AND',
        $insertData = array()
    ) {
        if (empty($findWhere)) {
            $findWhere = array();
        }
        if (empty($whereOperator)) {
            $whereOperator = 'AND';
        }
        $insertArray = array();
        $whereArray = array();
        $updateVals = array();
        foreach ((array)$insertData as $field => &$value) {
            $field = trim($field);
            $value = trim($value);
            $updateKey = sprintf(
                ':update_%s',
                $field
            );
            $updateVals[sprintf('update_%s', $field)] = $value;
            $key = sprintf(
                '`%s`.`%s`',
                $this->databaseTable,
                $this->databaseFields[$field]
            );
            $insertArray[] = sprintf(
                '%s=%s',
                $key,
                $updateKey
            );
            unset($value);
        }
        unset($updateKey);
        $findVals = array();
        if (count($findWhere) > 0) {
            foreach ($findWhere as $field => &$value) {
                $key = trim($field);
                if (is_array($value) && count($value) > 0) {
                    foreach ($value as $i => &$val) {
                        $val = trim($val);
                        // Define the key
                        $k = sprintf(
                            '%s_%d',
                            $key,
                            $i
                        );
                        // Define param keys
                        $findKeys[] = sprintf(
                            ':%s',
                            $k
                        );
                        // Define the param array
                        $findVals[$k] = $val;
                    }
                    $whereArray[] = sprintf(
                        '`%s`.`%s` IN (%s)',
                        $this->databaseTable,
                        $this->databaseFields[$field],
                        implode(',', $findKeys)
                    );
                    unset($findKeys);
                } else {
                    if (is_array($value)) {
                        $value = '';
                    }
                    $value = trim($value);
                    $k = sprintf(
                        '%s',
                        $key
                    );
                    // Define the param keys
                    $findKey = sprintf(
                        ':%s',
                        $key
                    );
                    // Define the param array
                    $findVals[$k] = $value;
                    $whereArray[] = sprintf(
                        '`%s`.`%s`%s%s',
                        $this->databaseTable,
                        $this->databaseFields[$field],
                        (
                            preg_match('#%#', (string)$value) ?
                            ' LIKE' :
                            '='
                        ),
                        $findKey
                    );
                }
            }
        }
        unset($findKeys, $findKey);
        $query = sprintf(
            $this->updateQueryTemplate,
            $this->databaseTable,
            implode(',', (array)$insertArray),
            (
                count($whereArray) ?
                sprintf(
                    ' WHERE %s',
                    implode(" $whereOperator ", (array)$whereArray)
                ) :
                ''
            )
        );
        $queryVals = array_merge(
            (array)$updateVals,
            (array)$findVals
        );
        return (bool)self::$DB->query($query, array(), $queryVals);
    }
    /**
     * Destroys items related to the main object
     *
     * @param array  $findWhere     what to find
     * @param string $whereOperator how to combine where items
     * @param string $orderBy       how to order fields
     * @param string $sort          how the sort order
     * @param string $compare       how to compare
     * @param string $groupBy       how to group fields
     * @param bool   $not           use not operator
     *
     * @return bool
     */
    public function destroy(
        $findWhere = array(),
        $whereOperator = 'AND',
        $orderBy = 'name',
        $sort = 'ASC',
        $compare = '=',
        $groupBy = false,
        $not = false
    ) {
        // Fail safe defaults
        if (empty($findWhere)) {
            $findWhere = array();
        }
        if (empty($whereOperator)) {
            $whereOperator = 'AND';
        }
        if (empty($sort)) {
            $sort = 'ASC';
        }
        $this->orderBy($orderBy);
        if (empty($compare)) {
            $compare = '=';
        }
        if (array_key_exists('id', $findWhere)) {
            $ids = $findWhere['id'];
        } else {
            $ids = $this->find(
                $findWhere,
                $whereOperator,
                $orderBy,
                $sort,
                $compare,
                $groupBy,
                $not,
                'id'
            );
        }
        $destroyVals = array();
        foreach ((array)$ids as $index => &$id) {
            $key = 'id';
            $destroyKeys[] = sprintf(
                ':%s_%d',
                $key,
                $index
            );
            $destroyVals[sprintf('%s_%d', $key, $index)] = $id;
            unset($id);
        }
        $query = sprintf(
            $this->destroyQueryTemplate,
            $this->databaseTable,
            $this->databaseTable,
            $this->databaseFields['id'],
            implode(',', (array)$destroyKeys)
        );
        return self::$DB->query($query, array(), $destroyVals);
    }
    /**
     * Builds a select box/option box from the elements
     *
     * @param mixed  $matchID     select the matching id
     * @param string $elementName the name for the select box
     * @param string $orderBy     how to order
     * @param string $filter      should we filter existing
     * @param mixed  $template    should we include a template element
     *
     * @return string
     */
    public function buildSelectBox(
        $matchID = '',
        $elementName = '',
        $orderBy = 'name',
        $filter = '',
        $template = false
    ) {
        global $node;
        if ($node === 'image') {
            $matchID = (
                $matchID === 0 ?
                1 :
                $matchID
            );
        }
        $elementName = trim($elementName);
        if (empty($elementName)) {
            $elementName = strtolower($this->childClass);
        }
        $this->orderBy($orderBy);
        $items = $this->find(
            $filter ? array('id' => $filter) : '',
            '',
            $orderBy,
            '',
            '',
            '',
            ($filter ? true : false)
        );
        ob_start();
        foreach ((array)$items as &$Object) {
            if (!$Object->isValid()) {
                continue;
            }
            if (array_key_exists('isEnabled', $this->databaseFields)
                && !$Object->get('isEnabled')
            ) {
                continue;
            }
            printf(
                '<option value="%s"%s>%s</option>',
                $Object->get('id'),
                (
                    $matchID == $Object->get('id') ?
                    ' selected' :
                    (
                        $template ?
                        sprintf('${selected_item%d}', $Object->get('id')) :
                        ''
                    )
                ),
                sprintf(
                    '%s - (%d)',
                    $Object->get('name'),
                    $Object->get('id')
                )
            );
        }
        $objOpts = ob_get_clean();
        $objOpts = trim($objOpts);
        if (empty($objOpts)) {
            return _('No items found');
        }
        $tmpStr = sprintf(
            '<select name="%s" autcomplete="off">'
            . '<option value="">- %s -</option>'
            . '%s</select>',
            ($template ? '${selector_name}' : $elementName),
            self::$foglang['PleaseSelect'],
            $objOpts
        );
        return $tmpStr;
    }
    public function exists($name, $id = 0, $idField = 'name')
    {
        if (empty($id)) {
            $id = 0;
        }
        if (empty($idField)) {
            $idField = 'name';
        }
        $query = sprintf(
            $this->existsQueryTemplate,
            $this->databaseTable,
            $this->databaseFields[$idField],
            $this->databaseTable,
            $this->databaseTable,
            $this->databaseFields[$idField],
            $name,
            $this->databaseTable,
            $this->databaseFields[$idField],
            $id
        );
        return (bool)self::$DB->query($query)->fetch()->get('total') > 0;
    }
    public function search($keyword = '', $returnObjects = false)
    {
        if (empty($keyword)) {
            $keyword = trim(self::$isMobile ? $_REQUEST['host-search'] : $_REQUEST['crit']);
        }
        $mac_keyword = join(':', str_split(str_replace(array('-', ':'), '', $keyword), 2));
        $mac_keyword = preg_replace('#[%\+\s\+]#', '%', sprintf('%%%s%%', $mac_keyword));
        if (empty($keyword)) {
            $keyword = '%';
        }
        if ($keyword === '%') {
            return self::getClass($this->childClass)->getManager()->find();
        }
        $keyword = preg_replace('#[%\+\s\+]#', '%', sprintf('%%%s%%', $keyword));
        $_SESSION['caller'] = __FUNCTION__;
        if (count($this->aliasedFields) > 0) {
            $this->arrayRemove($this->aliasedFields, $this->databaseFields);
        }
        $findWhere = array_fill_keys(array_keys($this->databaseFields), $keyword);
        $itemIDs = self::getSubObjectIDs($this->childClass, $findWhere, 'id', '', 'OR');
        $HostIDs = self::getSubObjectIDs('Host', array('name'=>$keyword, 'description'=>$keyword, 'ip'=>$keyword), '', '', 'OR');
        switch (strtolower($this->childClass)) {
            case 'user':
                break;
            case 'host':
                $HostIDs = self::getSubObjectIDs('MACAddressAssociation', array('mac'=>$mac_keyword, 'description'=>$keyword), 'hostID', '', 'OR');
                $HostIDs = array_merge($HostIDs, self::getSubObjectIDs('Inventory', array('sysserial'=>$keyword, 'caseserial'=>$keyword, 'mbserial'=>$keyword, 'primaryUser'=>$keyword, 'other1'=>$keyword, 'other2'=>$keyword, 'sysman'=>$keyword, 'sysproduct'=>$keyword), 'hostID', '', 'OR'));
                $ImageIDs = self::getSubObjectIDs('Image', array('name'=>$keyword, 'description'=>$keyword), '', '', 'OR');
                $GroupIDs = self::getSubObjectIDs('Group', array('name'=>$keyword, 'description'=>$keyword), '', '', 'OR');
                $SnapinIDs = self::getSubObjectIDs('Snapin', array('name'=>$keyword, 'description'=>$keyword), '', '', 'OR');
                $PrinterIDs = self::getSubObjectIDs('Printer', array('name'=>$keyword, 'description'=>$keyword), '', '', 'OR');
                if (count($ImageIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('Host', array('imageID'=>$ImageIDs)));
                }
                if (count($GroupIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('GroupAssociation', array('groupID'=>$GroupIDs), 'hostID'));
                }
                if (count($SnapinIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('SnapinAssociation', array('snapinID'=>$SnapinIDs), 'hostID'));
                }
                if (count($PrinterIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('PrinterAssociation', array('printerID'=>$PrinterIDs), 'hostID'));
                }
                $itemIDs = array_merge($itemIDs, $HostIDs);
                break;
            case 'image':
                if (count($HostIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('Host', array('id'=>$HostIDs), 'imageID'));
                }
                break;
            case 'task':
                $TaskStateIDs = self::getSubObjectIDs('TaskState', array('name'=>$keyword, 'description'=>$keyword), '', '', 'OR');
                $TaskTypeIDs = self::getSubObjectIDs('TaskType', array('name'=>$keyword, 'description'=>$keyword), '', '', 'OR');
                $ImageIDs = self::getSubObjectIDs('Image', array('name'=>$keyword, 'description'=>$keyword), '', '', 'OR');
                $GroupIDs = self::getSubObjectIDs('Group', array('name'=>$keyword, 'description'=>$keyword), '', '', 'OR');
                $SnapinIDs = self::getSubObjectIDs('Snapin', array('name'=>$keyword, 'description'=>$keyword), '', '', 'OR');
                $PrinterIDs = self::getSubObjectIDs('Printer', array('name'=>$keyword, 'description'=>$keyword), '', '', 'OR');
                if (count($ImageIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('Host', array('imageID'=>$ImageIDs)));
                }
                if (count($GroupIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('GroupAssociation', array('groupID'=>$GroupIDs), 'hostID'));
                }
                if (count($SnapinIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('SnapinAssociation', array('snapinID'=>$SnapinIDs), 'hostID'));
                }
                if (count($PrinterIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('PrinterAssociation', array('printerID'=>$PrinterIDs), 'hostID'));
                }
                if (count($TaskStateIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('Task', array('stateID'=>$TaskStateIDs)));
                }
                if (count($TaskTypeIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('Task', array('typeID'=>$TaskTypeIDs)));
                }
                if (count($HostIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs('Task', array('hostID'=>$HostIDs)));
                }
                break;
            default:
                $assoc = sprintf('%sAssociation', $this->childClass);
                $objID = sprintf('%sID', strtolower($this->childClass));
                if (!class_exists($assoc)) {
                    break;
                }
                if (count($itemIDs) && !count($HostIDs)) {
                    break;
                }
                $HostIDs = array_merge($HostIDs, self::getSubObjectIDs($assoc, array($objID=>$itemIDs), 'hostID'));
                if (count($HostIDs)) {
                    $itemIDs = array_merge($itemIDs, self::getSubObjectIDs($assoc, array('hostID'=>$HostIDs), $objID));
                }
                break;
        }
        $itemIDs = self::getSubObjectIDs($this->childClass, array('id'=>array_values(array_filter(array_unique($itemIDs)))));
        if ($returnObjects) {
            return self::getClass($this->childClass)->getManager()->find(array('id'=>$itemIDs));
        }
        return $itemIDs;
    }
}
