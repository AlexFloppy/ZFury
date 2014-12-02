<?php

namespace Categories\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use Categories\Form\Filter;
use Zend\Form\Annotation\AnnotationBuilder;
use DoctrineModule\Validator;
use Categories\Validators;

class ManagementController extends AbstractActionController
{

    public function indexAction()
    {
        $entityManager = $this
            ->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager');
        $repository = $entityManager->getRepository('Categories\Entity\Categories');

        $currentRootCategory = null;
        $categories = null;
        if ($id = $this->params('id')) {
            $currentRootCategory = $entityManager->getRepository('Categories\Entity\Categories')->findOneBy(['parentId' => null, 'id' => $id]);
        }
        $rootCategories = $entityManager->getRepository('Categories\Entity\Categories')->findBy(['parentId' => null]);

        if (!$currentRootCategory && !empty($rootCategories)) {
            $currentRootCategory = $rootCategories[0];
        }
        if ($currentRootCategory) {
            $categories = $repository->findBy(['parentId' => $currentRootCategory->getId()], ['order' => 'ASC']);
        }

        return new ViewModel(['categories' => $categories, 'rootTree' => $rootCategories, 'currentRoot' => $currentRootCategory]);

    }

    /**
     * @return ViewModel
     */
    public function createAction()
    {
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $category = new \Categories\Entity\Categories();
        $repository = $entityManager->getRepository('Categories\Entity\Categories');

        $parentId = $this->params('parentId');

        $orders = array();
        if (!$siblings = $repository->findBy(['parentId' => $parentId])) {
            $siblings = $repository->findBy(['parentId' => null]);
        }
        foreach ($siblings as $sibling) {
            $orders[] = $sibling->getOrder();
        }

        if (count($orders) > 0) {
            $order = max($orders) + 1;
        } else {
            $order = 1;
        }

        $category->setParentId($parentId);
        $category->setOrder($order);
        $builder = new AnnotationBuilder($entityManager);

        $form = $builder->createForm($category);
        $form->setHydrator(new DoctrineHydrator($entityManager));
        $form->bind($category);

        if ($this->getRequest()->isPost()) {
            $form->setInputFilter(new Filter\CreateInputFilter($this->getServiceLocator()));
            $form->setData($this->getRequest()->getPost());
            $aliasValid = new Validator\NoObjectExists(['object_repository' => $repository, 'fields' => ['alias', 'parentId']]);

            if ($form->isValid()) {
                $parentId = $this->getRequest()->getPost('parentId');
                if (empty($parentId)) {
                    $parentId = null;
                }
                if ($aliasValid->isValid(['alias' => $this->getRequest()->getPost('alias'),
                    'parentId' => $parentId])
                ) {
                    $entityManager->persist($category);
                    $entityManager->flush();

                    $this->flashMessenger()->addSuccessMessage('Category has been successfully added!');
                    return $this->redirect()->toRoute('categories/default', array('controller' => 'management', 'action' => 'index'));
                }
                $form->get('alias')->setMessages(array(
                    'errorMessageKey' => 'Alias must be unique in his category!'
                ));
            }
        }
        $viewModel = new ViewModel(['form' => $form, 'title' => 'Create category']);
        return $viewModel;
    }

    /**
     * @return ViewModel
     */
    public function editAction()
    {
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $repository = $entityManager->getRepository('Categories\Entity\Categories');

        $id = $this->params('id');
        if (!$category = $repository->find($id)) {
            $this->flashMessenger()->addErrorMessage('Invalid category id!');
            return $this->redirect()->toRoute('categories/default', array('controller' => 'management', 'action' => 'index'));
        }

        if ($category->getParentId()) {
            $parentId = $category->getParentId()->getId();
            $category->setParentId($parentId);
        }

        $builder = new AnnotationBuilder($entityManager);

        $form = $builder->createForm($category);
        $form->setHydrator(new DoctrineHydrator($entityManager));
        $form->bind($category);

        if ($this->getRequest()->isPost()) {
            $form->setInputFilter(new Filter\CreateInputFilter($this->getServiceLocator()));
            $form->setData($this->getRequest()->getPost());
            $aliasValid = new Validators\NoObjectExists($repository);

            if ($form->isValid()) {
                if ($aliasValid->isValid(['alias' => $this->getRequest()->getPost('alias'),
                    'parentId' => $this->getRequest()->getPost('parentId')], $id)
                ) {
                    $entityManager->persist($category);
                    $entityManager->flush();

                    $categoryService = $this->getServiceLocator()->get('Categories\Service\Categories');
                    $categoryService->updateChildrenPath($category);

                    $this->flashMessenger()->addSuccessMessage('Category has been successfully edited!');
                    return $this->redirect()->toRoute('categories/default', array('controller' => 'management', 'action' => 'index'));
                }
                $form->get('alias')->setMessages(array(
                    'errorMessageKey' => 'Alias must be unique in its category!'
                ));
            }
        }
        $viewModel = new ViewModel(['form' => $form, 'title' => 'Edit category']);
        return $viewModel;
    }

    /**
     * @return \Zend\Http\Response
     */
    public function deleteAction()
    {
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');

        $id = $this->params('id');
        if (!$category = $entityManager->find('Categories\Entity\Categories', $id)) {
            $this->flashMessenger()->addErrorMessage('Invalid category id!');
            return $this->redirect()->toRoute('categories/default', array('controller' => 'management', 'action' => 'index'));
        }

        $entityManager->remove($category);
        $entityManager->flush();

        $this->flashMessenger()->addSuccessMessage('Category has been successfully deleted!');
        return $this->redirect()->toRoute('categories/default', array('controller' => 'management', 'action' => 'index'));
    }

    /**
     * @return JsonModel
     */
    public function orderAction()
    {
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $repository = $entityManager->getRepository('Categories\Entity\Categories');

        $entityManager->getConnection()->beginTransaction();

        if ($this->getRequest()->isPost()) {
            $tree = $this->getRequest()->getPost('tree');
            $treeParent = $this->getRequest()->getPost('treeParent');
            try {
                $categories = json_decode($tree);
                if (!$categories) {
                    throw new \Exception('Categories tree is broken');
                }
                foreach ($categories as $node) {
                    if (isset($node->item_id)) {
                        $dbNode = $repository->findOneBy(['id' => $node->item_id]);

                        if (!$node->parent_id) {
                            $node->parent_id = $treeParent;
                        }

                        $parentId = $dbNode->getParentId()->getId();
                        if ($parentId != $node->parent_id && $node->parent_id) {
                            $dbNode->setParentId($repository->findOneBy(['id' => $node->parent_id]));
                        }

                        if ($dbNode->getOrder() != $node->order && $node->order) {
                            $dbNode->setOrder($node->order);
                        }
                        $entityManager->persist($dbNode);
                        $entityManager->flush();

                        $aliasValid = new Validators\NoObjectExists($repository);
                        if (!$aliasValid->isValid(['alias' => $dbNode->getAlias(),
                            'parentId' => $dbNode->getParentId()], $node->item_id)
                        ) {
                            throw new \Exception('Order has been failed!');
                        }

                    }
                }
                $entityManager->getConnection()->commit();
                $returnJson = new JsonModel(['success' => true]);
            } catch (\Exception $e) {
                $entityManager->getConnection()->rollback();
                $returnJson = new JsonModel(['success' => false]);
            }
            return $returnJson;
        }
        return $this->redirect()->toRoute('categories/default', array('controller' => 'management', 'action' => 'index'));
    }
}
