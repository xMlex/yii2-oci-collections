<?php
namespace xmlex\oci;

/**
 * Created by xMlex
 * Date: 25.01.16
 */
trait OciCollections
{

    /**
     * Current oracle db link
     * @return mixed
     */
    public function getDbLink()
    {
        if (!\Yii::$app->db->isActive)
            \Yii::$app->db->open();
        return \Yii::$app->db->pdo->_dbh;
    }

    /**
     *
     * Метод класса, возвращает коллекцию.
     *
     * @param array $array
     * @param string $type - default 'USER_DEF_VARCHAR_ARRAY'
     * @return OCI
     */
    public function createOciCollection(array $array, $type = 'USER_DEF_VARCHAR_ARRAY')
    {
        $collection = oci_new_collection($this->getDbLink(), $type);
        foreach ($array as $value) {
            $collection->append($value);
        }
        return $collection;
    }

    /**
     * Возвращает массив для вставки в #oci_bind_by_name
     * @see #createOciCollection
     * @param array $array
     * @param string $type
     * @return array массив для вставки в #oci_bind_by_name
     */
    public function createOciCollectionAsBind(array $array, $type = 'USER_DEF_VARCHAR_ARRAY')
    {
        return array('type' => SQLT_NTY, 'length' => -1, 'value' => $this->createOciCollection($array, $type));
    }

    /**
     * Метод класса. Получть содержимое запроса количеством $limit строк
     *
     * @param string $query строка
     * @param array|string $vars массив
     * @param int|string $length число
     * @param string $limit число
     *
     * @param int $keys
     * @return array
     * @throws \Exception
     */
    public function ociExecute($query, $vars = array(), $length = -1, $limit = null, $keys = OCI_ASSOC)
    {
        $parse = oci_parse($this->getDbLink(), $query);
        if (!empty($vars)) {
            foreach ($vars as $key => $value) {
                if (is_array($value)) {
                    oci_bind_by_name($parse, $key, $vars[$key]['value'], $value['length'], $value['type']);
                } else {
                    oci_bind_by_name($parse, $key, $vars[$key], $length);
                }
            }
        }
        $execute = oci_execute($parse, OCI_DEFAULT);
        if ($execute) {
            $array = false;
            if ($keys === OCI_ASSOC)
                $result = oci_fetch_all($parse, $array, null, $limit, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
            else
                $result = oci_fetch_all($parse, $array, null, $limit, OCI_FETCHSTATEMENT_BY_ROW + OCI_NUM + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
            oci_free_statement($parse);
            return $array;
        } else {
            $error_info = oci_error($parse);
            oci_rollback($this->getDbLink());
            oci_free_statement($parse);
            throw new \Exception($error_info['message'], $error_info['code']);
        }
    }

    /**
     * Выполняет запрос и возвращает результат
     * @param $query
     * @param $label - название пе
     * @param array $params
     * @param int $length
     * @return mixed
     * @throws \Exception
     */
    public function ociExecuteWithReturn($query, $label, array $params = [], $length = -1)
    {
        $parse = oci_parse($this->getDbLink(), $query);
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                oci_bind_by_name($parse, $key, $params[$key]['value'], $params[$key]['length'], $params[$key]['type']);
            } else {
                oci_bind_by_name($parse, $key, $params[$key], $length);
            }
        }
        oci_bind_by_name($parse, $label, $var, 20);
        $execute = oci_execute($parse, OCI_DEFAULT);
        if ($execute) {
            oci_commit($this->getDbLink());
            oci_free_statement($parse);
            return $var;
        } else {
            $error_info = oci_error($parse);
            oci_rollback($this->getDbLink());
            oci_free_statement($parse);
            throw new \Exception($error_info['message'], $error_info['code']);
        }
    }
}