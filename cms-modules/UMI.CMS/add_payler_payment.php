<?php

    $className = "payler";
    $paymentName = "Payler";
 
    include "standalone.php";
 
    $objectTypesCollection = umiObjectTypesCollection::getInstance();
    $objectsCollection = umiObjectsCollection::getInstance();
 
    // получаем родительский тип
    $parentTypeId = $objectTypesCollection->getTypeIdByGUID("emarket-payment");
 
    // Тип для внутреннего объекта, связанного с публичным типом
    $internalTypeId = $objectTypesCollection->getTypeIdByGUID("emarket-paymenttype");
    $typeId = $objectTypesCollection->addType($parentTypeId, $paymentName);
 
    // Создаем внутренний объект
    $internalObjectId = $objectsCollection->addObject($paymentName, $internalTypeId);
    $internalObject = $objectsCollection->getObject($internalObjectId);
    $internalObject->setValue("class_name", $className); // имя класса для реализации
 
    // связываем его с типом
    $internalObject->setValue("payment_type_id", $typeId);
    $internalObject->setValue("payment_type_guid", "user-emarket-payment-" . $typeId);
    $internalObject->commit();
 
    // Связываем внешний тип и внутренний объект
    $type = $objectTypesCollection->getType($typeId);
    $type->setGUID($internalObject->getValue("payment_type_guid"));
    $type->commit();
 
    echo "Способ оплаты Payler добавлен!";