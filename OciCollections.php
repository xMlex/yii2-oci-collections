<?php
namespace xmlex\oci;

/**
 * Created by xMlex
 * Date: 25.01.16
 */
trait OciCollections
{
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
        if (!\Yii::$app->db->isActive)
            \Yii::$app->db->open();
        $oracle_link = \Yii::$app->db->pdo->_dbh;
        $collection = oci_new_collection($oracle_link, $type);
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
     * @param string $length число
     * @param string $limit число
     *
     * @param int $keys
     * @return array
     */
    public function ociExecute($query, $vars = array(), $length = -1, $limit = null, $keys = OCI_ASSOC)
    {
        if (!\Yii::$app->db->pdo == null)
            \Yii::$app->db->open();
        $oracle_link = \Yii::$app->db->pdo->_dbh;
        $parse = oci_parse($oracle_link, $query);
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
            oci_rollback($oracle_link);
            oci_free_statement($parse);

            echo "<span style='color:red;'>При выполнении запроса возникла ошибка!</span><br/><b>Код:</b> " . $error_info['code'] . "<br/><b>Сообщение:</b> " . $error_info['message'] . "<br/><b>Запрос:</b><br/><pre>" . $query . "</pre>";
            exit;
            return false;
        }
    }

    /**
     * @param $ip_start_time
     * @param $ip_finish_time
     * @param $ip_projects - OCI_Bind со списокм проектов
     * @return array
     */
    public function getOperatorListByProjects($ip_start_time, $ip_finish_time, $ip_projects)
    {
        $operators_bind = [
            'ip_start_time' => $ip_start_time,
            'ip_finish_time' => $ip_finish_time,
            'ip_project_list' => $ip_projects,
        ];
        return $this->ociExecute('SELECT * FROM TABLE(pkg_common.fnc_get_existing_operators(
                                    ip_start_time => :ip_start_time,
                                    ip_finish_time => :ip_finish_time,
                                    ip_project_list => :ip_project_list))', $operators_bind);
    }

}