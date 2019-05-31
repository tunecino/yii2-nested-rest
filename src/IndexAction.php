<?php
/**
 * @link https://github.com/tunecino/yii2-nested-rest
 * @copyright Copyright (c) 2016 Salem Ouerdani
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace tunecino\nestedrest;

use Yii;
use yii\data\ActiveDataProvider;

/**
 * @author Salem Ouerdani <tunecino@gmail.com>
 */
class IndexAction extends Action
{
    /**
     * Prepares the data provider that should return the requested 
     * collection of the models within its related model.
     * @return ActiveDataProvider
     */
    public function run()
    {
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id);
        }

        $relModel = $this->getRelativeModel();
        $getter = 'get' . $this->relationName;

        return new ActiveDataProvider([
            'query' => $relModel->$getter(),
        ]);
    }
}
