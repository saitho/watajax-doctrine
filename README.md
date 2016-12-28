# watajax-doctrine
Doctrine implementation for Watajax

## Foreword

I created this class because I wanted to use WATAJAX under Symfony 2. I chose to use Watajax because jQuery DataTables can't handle >1000 entries very well (at least when I tried it a few years ago).

I'll probably not continue working on this class, so feel free to suggest changes or fork it.

## Installation

Please note that you'll still need the basic Watajax components which you'll find on Google Code: https://code.google.com/archive/p/watajax/
That also means the WatajaxSql.php class has to be included!

WATAJAX's javascript uses a numeric _table\_id_ to identify the table for requesting data from.
Trying to reflect this behaviour, you'll have to add the Model you want to use: ``WatajaxDoctrine::addTable(Vendor\Models\Classname::class);``

## New options
These options were tested for a m:1 relation in Symfony 2.

### dqlModelValue
Fields you want to fetch from the model in order to use them for replacing markers in transform options.

Example:
The marker !platformName will be replaced by the value of **$model->getPlatform()->getName()**.
Note that !LCplatformName has the modifier :lower which will turn the output into lowercase.
````
'platform' => [
   'virtual' => true,
   'name' => $this->get('translator')->trans('label.platform'),
   'dqlModelValue' => ['platformName'=>'platform->name','LCplatformName'=>'platform->name:lower'],
   'transform' => '!LCplatformName - !platformName'
],
````

#### Available modifiers (only for non-objects)

:lower = converts output to lowercase

:upper = converts output to UPPERCASE

:camel = converts output to camelCase

### Sorting by (m:1) relations

#### Parameters

dqlSortValue (database field that contains the relations, mandatory)

dqlSortFunc (MYSQL function, mandatory)

dqlSortReference (string, default: "id")

#### Example

One Product has multiple Features. Multiple Features have one Product.

````
class Product {
   /**
   * @ORM\Id
   * @ORM\GeneratedValue
   * @ORM\Column(type="integer")
   */
   private $id;
   
   /**
   * @var Collection<Feature>
   * @ORM\OneToMany(targetEntity="Feature", mappedBy="product")
   */
   private $keys;
}


class Feature {
   /**
   * @ORM\Id
   * @ORM\GeneratedValue
   * @ORM\Column(type="integer")
   */
   private $id;
   
   /**
   * @ORM\ManyToOne(targetEntity="Product", inversedBy="keys")
   * @ORM\JoinColumn(referencedColumnName="id")
   */
   private $product;
	
   /**
   * @ORM\Column(type="integer")
   */
   private $product_id;
}
````

````
'featureNumber' => [
    'virtual' => true,
    'name' => 'Features',
    'dqlSortValue' => 'features',
    'dqlSortReference' => 'id',
    'dqlSortFunc' => 'COUNT'
],
````

Here we sort by the amount (COUNT) of features (where Feature references to Product's *id*).