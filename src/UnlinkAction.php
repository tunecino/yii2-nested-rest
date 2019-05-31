<?php
/**
 * @link https://github.com/tunecino/yii2-nested-rest
 * @copyright Copyright (c) 2016 Salem Ouerdani
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace tunecino\nestedrest;

use Yii;
use yii\web\BadRequestHttpException;
use yii\helpers\StringHelper;

/**
 * UnlinkAction implements the API endpoint for unlinking models by primary keys.
 *
 * @author Salem Ouerdani <tunecino@gmail.com>
 */
class UnlinkAction extends Action
{
    /**
     * Unlinks two or more models by provided primary key or a list of primary keys.
     * If the relation type is a many_to_many. related row in the junction table will be deleted.
     * Otherwise related foreign key will be simply set to NULL.
     * A '204' response should be set to headers if any change has been made.
     * @param string $IDs should hold the list of IDs related to the models to be unlinken from the relative one.
     * it must be a string of the primary keys values separated by commas.
     * @throws BadRequestHttpException if any of the models are not linked.
     * @throws InvalidCallException if the models cannot be unlinked
     */
    public function run($IDs)
    {
        $relModel = $this->getRelativeModel();

        $modelClass = $this->modelClass;
        $pk = $modelClass::primaryKey()[0];
        $getter = 'get' . $this->relationName;
        $ids = preg_split('/\s*,\s*/', $IDs, -1, PREG_SPLIT_NO_EMPTY);

        $to_unlink = [];
        foreach ($ids as $pk_value) {
            $linked = $relModel->$getter()->andWhere([$pk => $pk_value])->exists();
            if ($linked === true) {
                $to_unlink[] = $this->findModel($pk_value);
            } else {
                throw new BadRequestHttpException(StringHelper::basename($modelClass) . " '$pk_value' not linked to ".StringHelper::basename($this->relativeClass)." '$this->relative_id'.");
            }
        }

        $relType = $relModel->getRelation($this->relationName);
        $delete = ($relType->multiple === true && $relType->via !== null);

        foreach ($to_unlink as $model) {
            if ($this->checkAccess) {
                call_user_func($this->checkAccess, $this->id, $model);
            }
            $relModel->unlink($this->relationName, $model, $delete);
        }
        Yii::$app->getResponse()->setStatusCode(204);
    }
}
