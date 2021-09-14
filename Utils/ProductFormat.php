<?php

namespace Openpay\Cards\Model\Utils;

class ProductFormat
{
    protected $productRepository;
    protected $categoryRepository;

    public function __construct
    ( 
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
    }
    
    /**
     * @var \Magento\Sales\Model\Order $order
     */
    public function getInfoProducts($order) {
        $products = array();
        $productSum = 0;
        $items = $order->getAllItems();
        $index = 0;
        foreach($items as $item){
            if(!$item->getParentItem()){
                $category = $this->getCategoryByProductId($item->getProductId());
                $productSum = $productSum + $item->getPrice() * $item->getQtyOrdered();
                $products[$index] = [
                    'id' => $item->getProductId(), 
                    'name' => $item->getName(), 
                    'price' => $item->getPrice(), 
                    'quantity' => $item->getQtyOrdered(),
                    'type' => ($item->getIsVirtual()) ? 'DIGITAL' : 'PHYSICAL'
                ];
                if ($category) {
                    $products[$index]['category'] = [
                        'id' => $category->getId(),
                        'name' => $category->getName()
                    ];
                }
                $index++;
            }
        }
        return [
            'products' => $products,
            'productSum' => $productSum
        ];
    }

    private function getCategoryByProductId($id){
        $product = $this->productRepository->getById($id);
        $categoriesIds = $product->getCategoryIds();
        foreach ($categoriesIds as $idCategory) {
            $category = $this->categoryRepository->get($idCategory);
            if (!$category->hasChildren()) {
                return $category;
            }
        }
    }
}

