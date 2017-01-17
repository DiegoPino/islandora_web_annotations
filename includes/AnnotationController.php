<?php
/**
 * @file
 * Handles the hookmenu calls from the web annotation module
 */

require_once('AnnotationConstants.php');
require_once('Annotation.php');
require_once('AnnotationContainer.php');

//ToDo: Need to validate input vars

function getRoot(){
    $RootInfo = '{ "name": "Islandora Web Annotations", "version": "1.0.0"}';
    echo $RootInfo;
    drupal_exit();
}

function searchForAnnotations(){
    $targetObjectURI = isset($_GET['uri']) ? $_GET['uri'] : '';
    $targetObjectID = substr($targetObjectURI, strrpos($targetObjectURI, '/') + 1);

    $oAnnotationContainer = new AnnotationContainer();
    $output = $oAnnotationContainer->getAnnotationContainer($targetObjectID);

    $annotationData = json_decode($output, true);
    $items = $annotationData["first"]["items"];

    $arrResult["rows"] = [];

    for($i = 0; $i < count($items); $i++) {
        array_push(  $arrResult["rows"], $items[$i]);
    }

    $arrResult["total"] = count($items);
    $output = json_encode($arrResult);

    watchdog(AnnotationConstants::MODULE_NAME, "Video Annotations - searchForAnnotations " . $output);

    echo $output;
    drupal_exit();
}

function createAnnotationForVideo(){
    $entityBody = file_get_contents('php://input');
    $annotationData = json_decode($entityBody, true);
    $uri = $annotationData["uri"];
    $created = $annotationData["created"];

    $annotationData["context"] = $uri;

    $annotationMetadata["created"] = $created;
    $annotationMetadata["author"] = "Nat";

    $targetPID = substr($uri, strrpos($uri, '/') + 1);

    $oAnnotationContainer = new AnnotationContainer();
    $output = $oAnnotationContainer->createAnnotation($targetPID, $annotationData, $annotationMetadata);

    $annotationData = json_decode($output, true);

    $arrResult["rows"] = [];
    array_push(  $arrResult["rows"], $annotationData);
    $arrResult["total"] = 1;

    $output = json_encode($arrResult);

    watchdog(AnnotationConstants::MODULE_NAME, "Video Annotations - createAnnotationForVideo " . $output);

    echo $output;
    drupal_exit();
}

function createAnnotation(){
    $targetObjectID = null;
    try
    {
        $targetObjectID = isset($_POST['targetPid']) ? $_POST['targetPid'] : '';
        $annotationData =  isset($_POST['annotationData']) ? $_POST['annotationData'] : '';
        $annotationMetadata =  isset($_POST['metadata']) ? $_POST['metadata'] : '';

        $oAnnotationContainer = new AnnotationContainer();
        $output = $oAnnotationContainer->createAnnotation($targetObjectID, $annotationData, $annotationMetadata);
    } catch(Exception $e) {
        watchdog(AnnotationConstants::MODULE_NAME, 'Error in createAnnotation: %t', array('%t' => $e->getMessage()), WATCHDOG_ERROR);
        $output = array('status' => "Fail", "msg"=> "Failed to create annotation for targetObjectID " . $targetObjectID);
        $output = json_encode($output);
    }

    drupal_json_output($output);
    drupal_exit();
}

function getAnnotationContainer(){
    $targetObjectID = null;

    try {
        $targetObjectID = isset($_GET['targetPid']) ? $_GET['targetPid'] : '';

        $oAnnotationContainer = new AnnotationContainer();
        $output = $oAnnotationContainer->getAnnotationContainer($targetObjectID);
    } catch(Exception $e)
    {
        watchdog(AnnotationConstants::MODULE_NAME, 'Error in getAnnotationContainer: %t', array('%t' => $e->getMessage()), WATCHDOG_ERROR);
        $output = array('status' => "failure", "msg"=> "Failed to get annotationContainer for targetObjectID " . $targetObjectID);
        $output = json_encode($output);
    }

    drupal_json_output($output);
    drupal_exit();
}


function updateAnnotation(){
    $annotationPID = null;

    try {
        parse_str(file_get_contents("php://input"), $putVars);
        $annotationData = $putVars['annotationData'] ? $putVars['annotationData'] : '';
        $annotationID = $annotationData["pid"];
        $annotationMetadata = $putVars['metadata'] ? $putVars['metadata'] : '';

        $ETag = $_SERVER['HTTP_IF_MATCH'];
        $oAnnotation = new Annotation();
        $bConflict = $oAnnotation->checkEditConflict($annotationID, $ETag);

        // If conflict exists, return message
        if($bConflict == false){
            $output = array('status' => "conflict", "data"=> "The original datastream has been changed for " . $annotationID);
            $output = json_encode($output);
        } else {
            $output = $oAnnotation->updateAnnotation($annotationData, $annotationMetadata);
            $output = array('status' => "success", "data"=> $output);
            $output = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

    } catch(Exception $e) {
        watchdog(AnnotationConstants::MODULE_NAME, 'Error in updateAnnotation: %t', array('%t' => $e->getMessage()), WATCHDOG_ERROR);
        $output = array('status' => "failure", "data"=> "Failed to updateAnnotation with annotation with PID " . $annotationID);
        $output = json_encode($output);
    }

    drupal_json_output($output);
    drupal_exit();
}

function deleteAnnotation(){
    $annotationID = null;
    try {
        parse_str(file_get_contents("php://input"), $deleteVars);
        $annotationID = $deleteVars['annotationID'] ? $deleteVars['annotationID'] : '';
        $annotationContainerID = $deleteVars['annotationContainerID'] ? $deleteVars['annotationContainerID'] : '';
        $ETag = $_SERVER['HTTP_IF_MATCH'];

        // Delete the object
        $oAnnotation = new Annotation();
        $bConflict = $oAnnotation->checkEditConflict($annotationID, $ETag);

        // If conflict exists, return message
        if($bConflict == false){
            $output = array('status' => "conflict", "data"=> "The original datastream has been changed for " . $annotationID);
            $output = json_encode($output);
        } else {
            $oAnnotationContainer = new AnnotationContainer();
            $output = $oAnnotationContainer->deleteAnnotation($annotationContainerID, $annotationID);
        }
    } catch(Exception $e)  {
        watchdog(AnnotationConstants::MODULE_NAME, 'Error in deleteAnnotation: %t', array('%t' => $e->getMessage()), WATCHDOG_ERROR);
        $output = array('status' => "failure", "data"=> "Failed to deleteAnnotation with annotation with PID " . $annotationID);
        $output = json_encode($output);
    }
    drupal_json_output($output);
    drupal_exit();
}