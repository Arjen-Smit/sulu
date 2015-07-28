<?php

/*
 * This file is part of the Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AdminBundle\Controller;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Sulu\Bundle\AdminBundle\Admin\AdminPool;
use Sulu\Bundle\AdminBundle\Admin\JsConfigPool;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AdminController
{
    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var AdminPool
     */
    private $adminPool;

    /**
     * @var JsConfigPool
     */
    private $jsConfigPool;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var EngineInterface
     */
    private $engine;

    /**
     * @var string
     */
    private $environment;

    /**
     * @var string
     */
    private $adminName;

    /**
     * @var array
     */
    private $locales;

    /**
     * @var string
     */
    private $suluVersion;

    /**
     * @var array
     */
    private $translatedLocales;

    /**
     * @var array
     */
    private $translations;

    /**
     * @var string
     */
    private $fallbackLocale;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        UrlGeneratorInterface $urlGenerator,
        TokenStorageInterface $tokenStorage,
        AdminPool $adminPool,
        JsConfigPool $jsConfigPool,
        SerializerInterface $serializer,
        EngineInterface $engine,
        $environment,
        $adminName,
        array $locales,
        $suluVersion,
        $translatedLocales,
        $translations,
        $fallbackLocale
    ) {
        $this->authorizationChecker = $authorizationChecker;
        $this->urlGenerator = $urlGenerator;
        $this->tokenStorage = $tokenStorage;
        $this->adminPool = $adminPool;
        $this->jsConfigPool = $jsConfigPool;
        $this->serializer = $serializer;
        $this->engine = $engine;
        $this->environment = $environment;
        $this->adminName = $adminName;
        $this->locales = $locales;
        $this->suluVersion = $suluVersion;
        $this->translatedLocales = $translatedLocales;
        $this->translations = $translations;
        $this->fallbackLocale = $fallbackLocale;
    }

    /**
     * Renders admin ui.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        if (!$this->authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return new RedirectResponse($this->urlGenerator->generate('sulu_admin.login', []));
        }

        $user = $this->tokenStorage->getToken()->getUser();

        // get js config from bundles
        $jsConfig = $this->jsConfigPool->getConfigParams();

        // render template
        if ($this->environment === 'dev') {
            $template = 'SuluAdminBundle:Admin:index.html.twig';
        } else {
            $template = 'SuluAdminBundle:Admin:index.html.dist.twig';
        }

        return $this->engine->renderResponse(
            $template,
            [
                'name' => $this->adminName,
                'locales' => $this->locales,
                'translated_locales' => $this->translatedLocales,
                'translations' => $this->translations,
                'fallback_locale' => $this->fallbackLocale,
                'suluVersion' => $this->suluVersion,
                'user' => json_decode(
                    $this->serializer->serialize(
                        $user,
                        'json',
                        SerializationContext::create()->setGroups(['frontend'])
                    )
                ),
                'config' => $jsConfig,
            ]
        );
    }

    /**
     * Returns a array of all bundles.
     *
     * @return JsonResponse
     */
    public function bundlesAction()
    {
        $admins = [];

        foreach ($this->adminPool->getAdmins() as $admin) {
            $name = $admin->getJsBundleName();
            if ($name !== null) {
                $admins[] = $name;
            }
        }

        return new JsonResponse($admins);
    }

    /**
     * Returns contexts of admin.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function contextsAction(Request $request)
    {
        $contexts = $this->adminPool->getSecurityContexts();
        $system = $request->get('system');

        $response = isset($system) ? $contexts[$system] : $contexts;

        return new JsonResponse($response);
    }

    /**
     * Returns config for admin.
     *
     * @return JsonResponse
     */
    public function configAction()
    {
        return new JsonResponse($this->jsConfigPool->getConfigParams());
    }
}
