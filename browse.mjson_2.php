<?php
// Start with an empty array of item metadata
$multipleItemMetadata = array();
// Loop through each item, picking up the minimum information needed.
// There will be no pagination, since the amount of information for each
// item will remain quite small.
/*
foreach( loop( 'item' ) as $item )
{
	if($itemMetadata = $this->itemJsonifier( $item , false)){
		array_push( $multipleItemMetadata, $itemMetadata );
	}

}
*/
// Querying DB is way faster than using itemJsonifier for large sets but is currently kind of ugly!
$itemIdArray = array();
foreach( $items as $item ){
	if($item->public){
		$itemIdArray[] = $item->id;
	}
}
$itemIdList=implode(',', $itemIdArray);
$db=get_db();
$prefix=$db->prefix;
$sql = "

/* Final Table - to plug into php below */

SELECT
FINAL.id_Final 'id',
FINAL.featured_Final 'featured',
FINAL.latitude_Final 'latitude',
FINAL.longitude_Final 'longitude',
FINAL.title_index_Final 'title_index',
FINAL.Title_Final 'title',
FINAL.address_Final 'address',
FINAL.filename_Final 'filename'

FROM


(
/* Table to include a Final File Id conditional - If there is a file with an order of '1', else choose the minimum value of item */
	SELECT t1.id_t1 'id_Final',
  t3.item_id_t3 'item_id_Final',
  t2.Title_t2 'Title_Final',
  t2.record_id_t2,
  t1.FileItemID_t1 AS title_index_Final,
  t1.latitude_t1 'latitude_Final',
  t1.longitude_t1 'longitude_Final',
  t3.MinID 'MinID_Final',
  t4.StreetAddress_t4 AS address_Final,
  t1.featured_t1 'featured_Final',
  t1.order_t1 'order_t1_Final',
  t3.order_t3 'order_t3_Final',
  t1.filename_t1 'filename_Final',
  t1.has_derivative_image_t1 'has_derivative_image_t1_Final',
  t3.has_derivative_image_t3 'has_derivative_image_t3_Final',
 (CASE
   WHEN (t1.order_t1 = 1 AND (t5.orderid = t1.FileItemID_t1))
   THEN t1.FileItemID_t1
	ELSE t3.MinID
END) AS FinalFileID_Final,
t5.orderid 'orderid_Final'

FROM
  (
		/* Table 1 (t1) - to include main Item, File and Location values - inner joins so that results only include items with both a location & corresponding file item */
  SELECT
    i.id 'id_t1',
    i.featured 'featured_t1',
    l.latitude 'latitude_t1',
    l.longitude 'longitude_t1',
    f.has_derivative_image 'has_derivative_image_t1',
    f.order 'order_t1',
    f.id 'FileItemID_t1',
    f.filename 'filename_t1'
  FROM
    (".$prefix."items as i
    INNER JOIN
      ".$prefix."locations as l
			 ON i.id = l.item_id)
  INNER JOIN
    ".$prefix."files as f ON i.id = f.item_id) AS t1

INNER JOIN
(
	/* Table 2 (t2) - includes an additional table with  element title with lowest record id
inner joins so that results only include items that have a corresponding element ID ti*/

SELECT et1.text AS Title_t2,
	et1.element_id 'element_id_t2',
	et1.id 'id_t2',
	et1.record_id 'record_id_t2'
FROM (
   	SELECT record_id,
	MIN(et1.id) AS MIN_ID2
FROM
".$prefix."element_texts AS et1
INNER JOIN ".$prefix."elements AS e1
ON et1.element_id = e1.id
WHERE et1.record_type = 'Item' AND e1.name = 'Title'
GROUP BY et1.record_id) AS x
INNER JOIN ".$prefix."element_texts AS et1 on et1.record_id = x.record_id and et1.id = x.MIN_ID2) AS t2
ON t1.id_t1 = t2.record_id_t2

LEFT JOIN
     (
			 /* Table 3 (t3) - creates table to include file item where derivative image is 1 and minimum file id
			 	left joins so null value will be included  in table */

    SELECT
        f2.item_id AS item_id_t3,
				f2.order AS order_t3,
      f2.has_derivative_image AS has_derivative_image_t3,
      MIN(f2.id) AS MinID
    FROM
      ".$prefix."files AS f2
    WHERE
      f2.has_derivative_image = 1
	 GROUP BY
      f2.item_id
  )AS t3 ON t3.item_id_t3 = t1.id_t1

LEFT JOIN
(
	/* Table 5 (t5) - creates OrderId conditional variable - gives value of 99 to orderID if there is no 1
	 left joins so null value will be included  in table */
	SELECT
f3.item_id 'item_id_t5',
f3.order 'order_t5',
(CASE
WHEN f3.order = 1
THEN f3.id
ELSE '99'
END) AS ORDERID,

f3.has_derivative_image
FROM
".$prefix."files AS f3
WHERE
f3.has_derivative_image = 1 AND f3.order = 1
GROUP BY f3.item_id) AS t5 ON t5.item_id_t5 = t1.id_t1

LEFT JOIN
(
	/* Table 4 (t4) - includes an additional MIN_ID table with lowest record id
left joins so that results include null when no address is available */

	 SELECT et3.text AS StreetAddress_t4,
	et3.id 'id_t4',
	et3.record_id 'record_id_t4',
	et3.element_id 'element_id_t4'
FROM (
   SELECT record_id,
	MIN(et4.id) AS MIN_ID
FROM
".$prefix."element_texts AS et4
JOIN ".$prefix."elements AS e4
ON et4.element_id = e4.id
WHERE et4.record_type = 'Item' AND e4.name = 'Street Address'
GROUP BY et4.record_id
) AS x INNER JOIN ".$prefix."element_texts AS et3 on et3.record_id = x.record_id AND et3.id = x.MIN_ID)t4
ON t1.id_t1  = t4.record_id_t4) AS FINAL

/* WHERE clause to limit to either order id is 1 - or there is there was no order id so lowest id taken  */

WHERE
FINAL.id_Final IN ($itemIdList)
AND
((FINAL.title_index_Final = FINAL.orderid_Final)
OR
(FINAL.orderid_Final IS NULL AND (FINAL.title_index_Final = FINAL.FinalFileID_Final)))



";
// @TODO: make sure to only get the FIRST file w/ derivative image (first by by assigned order then by lowest id)
// @TODO: make sure to only get the FIRST title (by lowest id)
$result_array = $db->fetchAll($sql);
// var_dump($result_array);
if ($result_array) {
	$already = 0; // var will be updated to check if record thumbnail has already been added
	foreach( $result_array as $record ){

		// Check ID and Location

		if (!isset($record['latitude']) || !isset($record['longitude'])) {
		   continue;
		}

		// Fix title
		$record['title'] = html_entity_decode(strip_formatting($record['title']));

		// Fix address - changed to empty string if null
		$record['address'] = isset($record['address']) ? $record['address'] : '';

		// Convert filename to thumbnail URL
		if (! is_null($record['filename'])) {
			// Replace any other extension with .jpg
			$filename = preg_replace('/\\.[A-Za-z]{3,4}/', '', $record['filename']) . ".jpg";
			$record['thumbnail'] = WEB_ROOT . "/files/square_thumbnails/$filename";
		}
		/* else
		 {
			// @TODO: this will be removed when we fix the initial query
			// if db query didn't find a "first file" use this slower method
			$r=get_record_by_id('item',$record['id']);
			if($r->hasThumbnail()){
				$t = $r->getFile();
				$record['thumbnail'] = WEB_ROOT . "/files/square_thumbnails/" . str_replace(array('.jpeg','.JPEG', '.JPG'), '.jpg', $t->filename) ;
			}

	}
	*/	unset($record['filename']);

		array_push($multipleItemMetadata, $record);
   }
}
// Title duplicate fix... a terrible hack :(
/* $postProcessed = [];

function sortByTitleIndex($a, $b) {
    return $a['title_index'] < $b['title_index'];
}
function sortById($a, $b) {
    return $a['id'] < $b['id'];
}
usort($multipleItemMetadata, 'sortByTitleIndex');
foreach($multipleItemMetadata as $a){
	unset($a['title_index']);
	$postProcessed[$a['id']] = $a;
}
usort($postProcessed, 'sortById');
$multipleItemMetadata = $postProcessed;
// End hack --- @TODO this will be removed when we fix the initial query
*/
$metadata = array(
	'items'        => $multipleItemMetadata,
	'total_items'  => count( $multipleItemMetadata )
);
echo Zend_Json_Encoder::encode( $metadata );
