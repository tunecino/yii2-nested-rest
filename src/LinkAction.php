<?php
/**
 * @link https://github.com/tunecino/yii2-nested-rest
 * @copyright Copyright (c) 2016 Salem Ouerdani
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace tunecino\nestedrest;

use Yii;
use yii\web\ServerErrorHttpException;
use yii\web\NotFoundHttpException;
use yii\helpers\StringHelper;

/**
 * LinkAction implements the API endpoint for linking models.
 *
 * @author Salem Ouerdani <tunecino@gmail.com>
 */
class LinkAction extends Action
{
    /**
     * @var string the scenario to be assigned to the model representing 
     * the junction table related data before it is validated and saved.
     */
    public $viaScenario = \yii\base\Model::SCENARIO_DEFAULT;

    /**
     * Links two or more models or updates the related data stored in a junction table.
     * A '204' response should be set to headers if any change has been made.
     * A '304' response should be set to headers if no change is made.
     * @param string $IDs should hold the list of IDs related to the models to be linken with the relative one.
     * it must be a string of the primary keys values separated by commas.
     * @throws NotFoundHttpException if model doesn't exist.
     * @throws ServerErrorHttpException if there is any error when linking the models
     * @throws ServerErrorHttpException if relation is many_to_many + both models are linked + no via() class provided + extraColumns provided via bodyParams.
     */
    public function run($IDs)
    {
        $relModel = $this->getRelativeModel();

        $modelClass = $this->modelClass;
        $pk = $modelClass::primaryKey()[0];
        $ids = preg_split('/\s*,\s*/', $IDs, -1, PREG_SPLIT_NO_EMPTY);
        $bodyParams = Yii::$app->request->bodyParams;
        $getter = 'get' . $this->relationName;

        $relType = $relModel->getRelation($this->relationName);

        $isManyToMany = ($relType->multiple === true && $relType->via !== null);
        $isManyToMany_viaClass = ($relType->multiple === true && is_array($relType->via));

        $to_link = [];
        foreach ($ids as $pk_value) {
            $linked = $relModel->$getter()->andWhere([$pk => $pk_value])->exists();
            
            if ($linked === false) {
                $exist = $modelClass::find()->andWhere([$pk => $pk_value])->exists();
                if ($exist === false) {
                    throw new NotFoundHttpException(StringHelper::basename($modelClass) . " '$pk_value' not found.");
                }  
                $to_link[] = $isManyToMany_viaClass ? $pk_value : $this->findModel($pk_value);
            }
        }

        if ($isManyToMany_viaClass) {
            // many_to_many relation and via class is set
            $viaRelation = $relType->via[1];
            $viaClass = $viaRelation->modelClass;

            if (count($to_link) === 0 && count($bodyParams)===0) {
                Yii::$app->getResponse()->setStatusCode(304);
            } else {
                foreach ($ids as $pk_value) {
                    if (in_array($pk_value, $to_link)) {
                        $viaModel = new $viaClass;
                        $viaModel->scenario = $this->viaScenario;

                        if ($this->checkAccess) {
                            call_user_func($this->checkAccess, $this->id, $viaModel);
                        }

                        $attributes = array_merge([
                            $this->linkAttribute => $this->relative_id,
                            $relType->link[$pk] => $pk_value
                        ],$bodyParams);

                        $viaModel->load($attributes, '');
                    } else {
                        // already linked -> update data in junction table.
                        $viaModel = $viaClass::findOne([
                            $this->linkAttribute => $this->relative_id,
                            $relType->link[$pk] => $pk_value
                        ]);

                        if ($this->checkAccess) {
                            call_user_func($this->checkAccess, $this->id, $viaModel);
                        }

                        $viaModel->scenario = $this->viaScenario;
                        $viaModel->load($bodyParams, '');
                    }

                    if ($viaModel->save() === false && !$viaModel->hasErrors()) {
                        throw new ServerErrorHttpException('Failed to update the object for unknown reason.');
                    } else if ($viaModel->hasErrors()) {
                        return $viaModel;
                    }
                }
                Yii::$app->getResponse()->setStatusCode(204);
            }
        }

        else {
            // could be many_to_many viaTable (no class) or whatever else relation
            $extraColumns = $isManyToMany ? $bodyParams : [];
            
            // junction table update is expected. inserting 2nd record won't be valid solution.
            if (count($to_link) === 0 && count($extraColumns) > 0) {
                throw new ServerErrorHttpException('objects already linked.');
            } else if (count($to_link) === 0) {
                Yii::$app->getResponse()->setStatusCode(304);
            } else {
                foreach ($to_link as $model) {
                    if ($this->checkAccess) {
                        call_user_func($this->checkAccess, $this->id, $model);
                    }
                    $relModel->link($this->relationName, $model, $extraColumns);
                }
                Yii::$app->getResponse()->setStatusCode(204);
            }
        }
    }
}
