<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once('ebay-config.php');
$itemId = "174951959939";
$apiEndPoint="https://api.ebay.com/ws/api.dll";

/***********Getting Item IDs of Bestselling****************/
$requestXml = '<?xml version="1.0" encoding="utf-8"?>';
$requestXml .= '<GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
$requestXml .= '<RequesterCredentials>';
$requestXml .= '<eBayAuthToken>'.$authToken.'</eBayAuthToken>';
$requestXml .= '</RequesterCredentials>';
$requestXml .= '<ErrorLanguage>en_US</ErrorLanguage>';
$requestXml .= "<WarningLevel>High</WarningLevel>";
$requestXml .= "<DetailLevel>ReturnAll</DetailLevel>";
$requestXml .= "<IncludeItemSpecifics>true</IncludeItemSpecifics>";
$requestXml .= '<ItemID>'.$itemId.'</ItemID>';
$requestXml .= "</GetItemRequest>";

$headers = array (
        "X-EBAY-API-SITEID: $SiteID",
        "X-EBAY-API-COMPATIBILITY-LEVEL: $comLvl",
        "X-EBAY-API-CALL-NAME: $apicall",
        "X-EBAY-API-IAF-TOKEN: $token",
    );
$ch = curl_init($apiEndPoint); 
curl_setopt($ch, CURLOPT_POST, true); 
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestXml); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false); 
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
$responseXML = curl_exec($ch); 
curl_close($ch);
$resp = simplexml_load_string($responseXML);

//var_dump($responseXML);

if($resp->Ack != 'Success') 
{
    echo "<br><br>Store : Not Found<br><br>";
    exit;
} 
else
{

    // Encode this xml data into json using json_encode function
    $jsonEncode = json_encode($resp, JSON_PRETTY_PRINT);
    $jsonData = json_decode($jsonEncode);

    // file_put_contents("output2.json", $jsonEncode);
    // $jsonData = json_decode($resp);  


    //get child sku, attri, etc values...
    $variations = $jsonData->Item->Variations->Variation;
    $variationCount = count($variations);
    $sku = array();
    $attName = array();
    $attVals = array();
    foreach($variations as $key => $variation){
        $sku[] = $variation->SKU;
        $attVal = array();
        foreach($variation->VariationSpecifics->NameValueList as $specifications){
            $attVal[] = $specifications->Value;
        }
        $attVals[] = $attVal;
    }
    foreach($variations[0]->VariationSpecifics->NameValueList as $specifications){
        $attName[] = $specifications->Name;
    }
    $skuData = implode('","', $sku);

    // get specification values...
    $specs = array();
    $variationSpecs = $jsonData->Item->Variations->VariationSpecificsSet->NameValueList;
    foreach($variationSpecs as $key => $variationSpec){
        $specs[] = '{"name":"'.$variationSpec->Name.'","values": ["'.implode('","', $variationSpec->Value).'"]}';
        var_dump($specs);
    }
    $specsData = implode(',', $specs);

    // format variationProduct details...
    $aspects = array();
    $variationProducts = array();
    for($i = 0; $i < $variationCount; $i++){
        $aspect = array();
        for($j = 0; $j < count($attName); $j++){
            $aspect[] = '"'.$attName[$j].'": ["'.$attVals[$i][$j].'"]';
        }
        $aspects[] = $aspect;
        if ($variations[$i]->VariationProductListingDetails->EAN) {
            $ean = '"'.$variations[$i]->VariationProductListingDetails->EAN.'"';
        }
        else{
            $ean = "[]";
        }
        $variationProducts[] = '
        {
            "sku": "'.$sku[$i].'", 
            "aspects": {'.implode(',', $aspects[$i]).'}, 
            "imageIds": [],
            "availability": {},
            "condition": "NEW",
            "packageWeightAndSize": {
                "packageType": "'.$jsonData->Item->ShippingPackageDetails->ShippingPackage.'",
                "weight": {
                  "value": 1,
                  "unit": "KILOGRAM"
                }
            },
            "ean": '.$ean.'
        }';

    }
    $variationProducts = implode(',', $variationProducts);


    // format variationOffers details...
    $variationOffers = array();
    for($i = 0; $i < $variationCount; $i++){
        $variationOffers[] = '
        {
            "sku": "'.$sku[$i].'", 
            "pricingSummary": {
                "price": {
                    "value": '.$variations[$i]->StartPrice.',
                    "currency": '.$jsonData->Item->Currency.'
                }
            }, 
            "availableQuantity": {},
            "categoryId": '.$jsonData->Item->PrimaryCategory->CategoryID.',
            "ebayCategoryNames": [
                ""
            ],
            "storeCategoryNames": [
                ""
            ],
            "listingPolicies": {
                "fulfillmentPolicyId": [],
                "paymentPolicyId": [],
                "returnPolicyId": []
            },
            "tax": {
                "applyTax": '.$jsonData->Item->ShippingDetails->SalesTax->ShippingIncludedInTax.',
                "vatPercentage": '.$jsonData->Item->VATDetails->VATPercent.'
            },
            "format": "'.$jsonData->Item->ListingType.'",
            "listingDuration": "'.$jsonData->Item->ListingDuration.'"
        }';

    }
    $variationOffers = implode(',', $variationOffers);

    // format all data...
    $outData = '
    {
        "parent": {
            "sku": "'.$jsonData->Item->SKU.'",
            "title": "'.$jsonData->Item->Title.'",
            "description": "'.$jsonData->Item->Description.'",
            "listingDescriptionTemplateId": null,
            "aspects": {
              "dynamic": {
                "Brand": [
                  "Unbranded"
                ],
                "Department": [
                  "Men"
                ],
                "Type": [
                  "T-Shirt"
                ]
              },
              "custom": {}
            },
            "image": {
                "PictureURL" : ["'.$jsonData->Item->PictureDetails->PictureURL[0].'"]
            },
            "variantSKUs": ["'.$skuData.'"],
            "variesBy": {
                "aspectsImageVariesBy": ["'.$jsonData->Item->Variations->Pictures->VariationSpecificName.'"],
                "specifications": ['.$specsData.']
                } 
        },
        "variation_products": ['.$variationProducts.'],
        "variation_offers": ['.$variationOffers.']
        
    }
    ';

    echo "<pre>";
    echo $outData;
    echo "</pre>";
    echo '</br>';

    // file_put_contents("output1.json", $outData); 

    $x = "";
    $var = '"hi"'.($x ? "success" : "");

    echo $var;
}

?>