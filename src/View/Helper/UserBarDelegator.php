<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Renderer\RendererInterface;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\User;
use Omeka\View\Helper\UserBar;

/**
 * View helper for rendering the user bar.
 *
 * Same of the Omeka user bar, except for guest users, who cannot go admin, and
 * a default different link for the account.
 *
 * @todo Update core to fill links simpler.
 *
 * Copy:
 * @see \AdvancedSearch\View\Helper\UserBarDelegator
 * @see \Guest\View\Helper\UserBarDelegator
 * @see \GuestPrivate\View\Helper\UserBarDelegator
 */
class UserBarDelegator extends UserBar
{
    /**
     * The default partial view script when the user has no right to go admin.
     */
    const PARTIAL_NAME_GUEST = 'common/user-bar-guest';

    /**
     * @var UserBar
     */
    protected $userBar;

    public function __construct(UserBar $userBar)
    {
        $this->userBar = $userBar;
    }

    public function __invoke($partialName = null)
    {
        $view = $this->getView();

        $site = $view->vars()->site;
        if (empty($site)) {
            return '';
        }

        $showUserBar = $view->siteSetting('show_user_bar', 0);
        if ($showUserBar == -1) {
            return '';
        }

        /** @var \Omeka\Entity\User $user */
        $user = $view->identity();
        if ($showUserBar != 1 && !$user) {
            return '';
        }

        $locale = null;

        $hasAdminRights = $user && $view->userIsAllowed('Omeka\Controller\Admin\Index');
        if (!$hasAdminRights && !$view->siteSetting('guest_show_user_bar')) {
            return '';
        }

        if ($user) {
            if ($hasAdminRights) {
                $locale = $view->userSetting('locale', null, $user->getId()) ?: ($view->setting('locale') ?: null);
                $links = $this->links($view, $site, $user, $locale);
                $partialName = $partialName ?: self::PARTIAL_NAME;
            } else {
                $links = [];
                $partialName = $partialName ?: self::PARTIAL_NAME_GUEST;
            }
        } else {
            $links = [];
            $partialName = $partialName ?: self::PARTIAL_NAME;
        }

        return $view->partial(
            $partialName,
            [
                'site' => $site,
                'user' => $user,
                'links' => $links,
                'userLocale' => $locale,
            ]
        );
    }

    protected function links(RendererInterface $view, SiteRepresentation $site, User $user, ?string $locale = null)
    {
        $links = parent::links($view, $site, $user, $locale);
        // If already filled (board, site and something), don't change them.
        if (!$links || count($links) > 2) {
            return $links;
        }

        // There is no default label for resources, so get it from the controller (sometime upper-cased).
        $params = $view->params()->fromRoute();
        $controller = $params['__CONTROLLER__'] ?? $params['controller'] ?? null;
        $controllers = [
            'AdvancedSearch\Controller\SearchController' => 'advanced-search', // @ŧranslate
            // Deprecated.
            'AdvancedSearch\Controller\IndexController' => 'advanced-search', // @ŧranslate
        ];

        if (!isset($controllers[$controller])) {
            return $links;
        }

        // Rights to admin is required.
        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $site->getServiceLocator()->get('Omeka\Acl');
        if (!$acl->isAdminRole($user->getRole())) {
            return;
        }

        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');
        $translate = $plugins->get('translate');

        // The resource is used only to create a class in partial.
        $links[] = [
            // Don't use class "advanced-search" that is used in theme.
            'resource' => 'advanced-search-config',
            'action' => 'browse',
            'text' => $translate('Search manager', null, $locale), // @translate
            'url' => $url('admin/search-manager'),
        ];

        $links[] = [
            'resource' => 'advanced-search-config',
            'action' => 'browse',
            'text' => $translate('Search config', null, $locale), // @translate
            'url' => $url('admin/search-manager/config-id', ['id' => $params['id'], 'action' => 'configure']),
        ];

        return $links;
    }
}
