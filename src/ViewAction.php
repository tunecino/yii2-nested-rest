<?php
/**
 * @link https://github.com/tunecino/yii2-nested-rest
 * @copyright Copyright (c) 2016 Salem Ouerdani
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace tunecino\nestedrest;

use Yii;

/**
 * ViewAction implements the API endpoint for returning the detailed information 
 * about a model or a list of models only if it is (or they are) linked to the relative one.
 *
 * @author Salem Ouerdani <tunecino@gmail.com>
 */
class ViewAction extends NestedAction
{
	/**
     * Displays a model or a list of provided models.
     * @param string $id the related primary key(s) of the model or list of models.
     * @return \yii\db\ActiveRecordInterface the model(s) being displayed
     */
    public function run($id)
    {
        $model = $this->findCurrentModel($id);
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id, $model);
        }

        return $model;
    }
}
