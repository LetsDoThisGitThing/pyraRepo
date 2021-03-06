<?php
use Interop\Container\ContainerInterface;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


class AppPage extends CorePage {
	public function __construct(ContainerInterface $ci) { 
		parent::__construct($ci);
	}

/////////////////////////////////////////////////////////////////////////////////////////////
// Model
	private function searchApp($q) {
		$_ = $this->trans;
		$match = "%$q%";
		$ret = [];
		$ret['title'] = $_('Searching apps: ').$q;
		$ret['body'] = [];
		$s = $this->db->prepare('select a.id, a.name, a.comments, a.icon, p.id as dbp_id, p.str_id as dbp_str_id, p.name as dbp_name, v.version, v.path, ar.name as arch, u.username, v.timestamp  * 1000.0 as timestamp, ifnull(a.infos,p.infos) as infos, v.md5sum, v.sha1sum, v.filesize
  from	apps a, dbpackages p, package_versions v, archs ar, users u
 where a.dbp_id=p.id
   and p.last_vers=v.id
   and p.arch_id=ar.id
   and u.id=v.by_user
   and a.enabled!=0
   and p.enabled!=0
   and v.enabled!=0
   and a.name like :match
order by timestamp desc');
		$s->bindParam(':match',	$match,		PDO::PARAM_STR);
		$s->execute();
		while($r = $s->fetch()) {
			$r['icon'] = $GLOBALS['repo_base'].$r['icon'];
			$r['dbp_url'] = $this->router->pathFor('packages.byStr', array('str'=> $r['dbp_str_id']));
			$r['url'] = $this->router->pathFor('apps.byId', array('id'=> $r['id']));
			$ret['body'][] = $r;
		}
		return $ret;
	}

	private function getAppsByCat($id) {
		$_ = $this->trans;
		$ret = [];
		$ret['title'] = $_('Category apps');
		$ret['body'] = [];
		$s = $this->db->prepare('select a.id, a.name, a.comments, a.icon, p.id as dbp_id, p.str_id as dbp_str_id, p.name as dbp_name, v.version, v.path, ar.name as arch, u.username, v.timestamp  * 1000.0 as timestamp, ifnull(a.infos,p.infos) as infos, v.md5sum, v.sha1sum, v.filesize
  from	apps a, dbpackages p, package_versions v, archs ar, users u, apps_categories c
 where a.dbp_id=p.id
   and p.last_vers=v.id
   and p.arch_id=ar.id
   and u.id=v.by_user
   and a.enabled!=0
   and p.enabled!=0
   and v.enabled!=0
   and a.id = c.app_id
   and c.cat_id=:id
order by timestamp desc;');
		$s->bindParam(':id',	$id,	PDO::PARAM_INT);
		$s->execute();
		while($r = $s->fetch()) {
			$r['dbp_url'] = $this->router->pathFor('packages.byStr', array('str'=> $r['dbp_str_id']));
			$r['icon'] = $GLOBALS['repo_base'].$r['icon'];
			$r['url'] = $this->router->pathFor('apps.byId', array('id'=> $r['id']));
			$ret['body'][] = $r;
		}
		return $ret;
	}

	private function getApps() {
		$_ = $this->trans;
		$ret = [];
		$ret['title'] = $_('All apps');
		$ret['body'] = [];
		$s = $this->db->prepare('select a.id, a.name, a.comments, a.icon, p.id as dbp_id, p.str_id as dbp_str_id, p.name as dbp_name, v.version, v.path, ar.name as arch, u.username, v.timestamp  * 1000.0 as timestamp, ifnull(a.infos,p.infos) as infos, v.md5sum, v.sha1sum, v.filesize
  from	apps a, dbpackages p, package_versions v, archs ar, users u
 where a.dbp_id=p.id
   and p.last_vers=v.id
   and p.arch_id=ar.id
   and u.id=v.by_user
   and a.enabled!=0
   and p.enabled!=0
   and v.enabled!=0
order by timestamp desc');
		$s->execute();
		while($r = $s->fetch()) {
			$r['icon'] = $GLOBALS['repo_base'].$r['icon'];
			$r['dbp_url'] = $this->router->pathFor('packages.byStr', array('str'=> $r['dbp_str_id']));
			$r['url'] = $this->router->pathFor('apps.byId', array('id'=> $r['id']));
			$ret['body'][] = $r;
		}
		return $ret;
	}

	private function getApp($id) {
		$s = $this->db->prepare('select a.id, a.name, a.enabled, a.comments, a.icon, p.id as dbp_id, p.str_id as dbp_str_id, p.name as dbp_name, v.version, v.path, ar.name as arch, u.username, v.timestamp * 1000.0 as timestamp, ifnull(a.infos,p.infos) as infos, v.md5sum, v.sha1sum, v.filesize
  from	apps a, dbpackages p, package_versions v, archs ar, users u
 where a.dbp_id=p.id
   and p.last_vers=v.id
   and p.arch_id=ar.id
   and u.id=v.by_user
   and p.enabled!=0
   and v.enabled!=0
   and a.id=:id');
		$s->bindParam(':id',	$id,		PDO::PARAM_INT);
		$s->execute();
		if ($r = $s->fetch()) {
			$r['icon'] = $GLOBALS['repo_base'].$r['icon'];
			$r['dbp_url'] = $this->router->pathFor('packages.byStr', array('str'=> $r['dbp_str_id']));
			$r['url'] = $this->router->pathFor('apps.byId', array('id'=> $r['id']));
			return $r;
		}
		return null;
	}

	private function addScreenshot($a, $ts, $path) {
		$s = $this->db->prepare('insert into app_shoots(app_id,timestamp,user_id, path) values(:id, :ts, :uid, :path)');
		$u = $this->auth->getUserId();
		$s->bindParam(':id',	$a['id'],	PDO::PARAM_INT);
		$s->bindParam(':ts',	$ts,		PDO::PARAM_INT);
		$s->bindParam(':uid',	$u,		PDO::PARAM_INT);
		$s->bindParam(':path',	$path,		PDO::PARAM_STR);
		$s->execute();
	}

	private function getLikes($id) {
		$ret = [];
		$u = $this->auth->getUserId();
		$s = $this->db->prepare('select t.total, u.me from 
(select count(*) as total from app_likes where app_id=:id) t,
(select count(*) as me from app_likes where app_id=:id and user_id=:uid) u');
		$s->bindParam(':id',	$id,	PDO::PARAM_INT);
		$s->bindParam(':uid',	$u,	PDO::PARAM_INT);
		$s->execute();
		if ($r = $s->fetch()) {
			$r['url'] = $this->router->pathFor('apps.like', array('id'=> $id));
			return $r;
		}
		$ret['total'] = 0;
		$ret['me'] = 0;
		return $ret;
	}
	private function getScreenShots($id) {
		$ret = [];
		$s = $this->db->prepare('select s.path as url, s.timestamp as alt
  from app_shoots s, packages_maintainers m, apps a
 where s.app_id=a.id
   and s.user_id=m.user_id
   and m.dbp_id=a.dbp_id
   and a.id=:id
 order by s.timestamp desc
 limit 0,10');
		$s->bindParam(':id',	$id,	PDO::PARAM_INT);
		$s->execute();
		while($r = $s->fetch()) {
			$r['url'] = $GLOBALS['repo_base'].$r['url'];
			$ret[] = $r;
		}
		return $ret;
	}

	private function getCommunityScreenShots($id) {
		$ret = [];
		$s = $this->db->prepare('select s.path as url, s.timestamp as alt
  from app_shoots s, packages_maintainers m, apps a
 where s.app_id=a.id
   and s.user_id!=m.user_id
   and m.dbp_id=a.dbp_id
   and a.id=:id
 order by s.timestamp desc
 limit 0,10');
		$s->bindParam(':id',	$id,	PDO::PARAM_INT);
		$s->execute();
		while($r = $s->fetch()) {
			$r['url'] = $GLOBALS['repo_base'].$r['url'];
			$ret[] = $r;
		}
		return $ret;
	}

	private function getVersionHistory($id) {
		$ret = [];
		$s = $this->db->prepare('select v.id, v.timestamp  * 1000.0 as timestamp, v.version, u.username as uploader, p.str_id as dbp_str_id, v.md5sum, v.sha1sum, v.filesize
  from package_versions v, apps a, users u, dbpackages p
 where v.dbp_id = a.dbp_id
   and p.id = v.dbp_id
   and u.id = v.by_user
   and v.enabled=1
   and a.id=:id
 order by v.timestamp desc');
		$s->bindParam(':id',	$id,	PDO::PARAM_INT);
		$s->execute();
		while($r = $s->fetch()) {
			$ret[] = array(
				'ts'	=> array( 'text' => $this->formatTimestamp($r['timestamp'])),
				'ver'	=> array( 'text' => $r['version']),
				'maint'	=> array( 'text' => $r['uploader']),
				'actions'	=> array(array( 'icon' => 'fa fa-download', 'url' => $this->router->pathFor('packages.download.version', array('id'=> $r['id'], 'str' => $r['dbp_str_id']))))
			);
		}
		return $ret;
	}

	private function updateDesc($id, $desc) {
		$ret = [];
		$s = $this->db->prepare('update apps set infos=:desc where id=:id');
		if ($desc=="") $desc=null;
		$s->bindParam(':id',	$id,	PDO::PARAM_INT);
		$s->bindParam(':desc',	$desc,	PDO::PARAM_STR);
		$s->execute();
	}

	private function getComments($id) {
		$ret = [];
		$ret['body'] = [];
		$s = $this->db->prepare('select u.username as author, c.timestamp * 1000.0 as timestamp, c.text
  from app_comments c, users u 
 where c.user_id=u.id
   and c.app_id=:id 
 order by c.timestamp asc');
		$s->bindParam(':id',	$id,	PDO::PARAM_INT);
		$s->execute();
		while($r = $s->fetch()) {
			$ret['body'][] = $r;
		}
		return $ret;
	}

	private function addComment($id, $comm) {
		$ret	= [];
		$date	= new DateTime();
		$ts	= $date->getTimestamp();
		$u = $this->auth->getUserId();
		if ($comm=="") return;
		$s = $this->db->prepare('insert into app_comments(app_id, timestamp, user_id, text) values(:id, :ts, :uid, :comm)');
		$s->bindParam(':id',	$id,	PDO::PARAM_INT);
		$s->bindParam(':ts',	$ts,	PDO::PARAM_INT);
		$s->bindParam(':uid',	$u,	PDO::PARAM_INT);
		$s->bindParam(':comm',	$comm,	PDO::PARAM_STR);
		$s->execute();
	}
	private function toggleLike($id) {
		$ret	= [];
		$u = $this->auth->getUserId();
		$c = $this->db->prepare('select count(*) as cnt from app_likes where user_id=:uid and app_id=:id');
		$c->bindParam(':id',	$id,	PDO::PARAM_INT);
		$c->bindParam(':uid',	$u,	PDO::PARAM_INT);
		$c->execute();
		$r = $c->fetch();
		if ($r['cnt']>0) {
			$s = $this->db->prepare('delete from app_likes where user_id=:uid and app_id=:id');
			$s->bindParam(':id',	$id,	PDO::PARAM_INT);
			$s->bindParam(':uid',	$u,	PDO::PARAM_INT);
			$s->execute();
			return false;
		} else {
			$date	= new DateTime();
			$ts	= $date->getTimestamp();
			$s = $this->db->prepare('insert into app_likes(app_id, timestamp, user_id) values(:id, :ts, :uid)');
			$s->bindParam(':id',	$id,	PDO::PARAM_INT);
			$s->bindParam(':uid',	$u,	PDO::PARAM_INT);
			$s->bindParam(':ts',	$ts,	PDO::PARAM_INT);
			$s->execute();
			return true;
		}
	}
	private function toggleEnable($a) {
		if ($a['enabled'] == 1) {
			$d = $this->db->prepare('update apps set enabled=false where id=:id');
			$d->bindParam(':id',	$a['id'],PDO::PARAM_INT);
			$d->execute();
		} else {
			$e = $this->db->prepare('update apps set enabled=true where id=:id');
			$e->bindParam(':id',	$a['id'],PDO::PARAM_INT);
			$e->execute();
		}
	}


/////////////////////////////////////////////////////////////////////////////////////////////
// Page Controlers

	public function appsPage (Request $request, Response $response) {
		$q = $request->getQueryParam('q');
		if (empty($q))
			$apps = $this->getApps();
		else
			$apps = $this->searchApp($q);
		$this->menu->breadcrumb = array(
			array('name' => 'Apps', 'icon' => 'fa fa-rocket', 'url' => $this->router->pathFor('apps.list'))
		);
 		return $this->view->render($response, 'apps.twig', [
			'apps'	=> $apps
 		]);
	}

	public function appsByCatPage (Request $request, Response $response) {
		$id = $request->getAttribute('id');
		$apps = $this->getAppsByCat($id);
		$this->menu->breadcrumb = array(
			array('name' => 'Apps', 'icon' => 'fa fa-rocket', 'url' => $this->router->pathFor('apps.list'))
		);
 		return $this->view->render($response, 'apps.twig', [
			'apps'	=> $apps
 		]);
	}

	public function appByIdPage (Request $request, Response $response) {
		$_	= $this->trans;
		$id = $request->getAttribute('id');
		$a  = $this->getApp($id);
		if (!is_array($a)) {
			$this->flash->addMessage('error', $_('No app ').$id.$_(' found'));
			return $response->withRedirect($this->router->pathFor('apps.list'));
		}
		if ($this->isPackageMaintainer($a['dbp_id']))
			$this->menu->isMaintainer  = true;
		else if ($a['enabled'] == 0) {
			$this->flash->addMessage('error', $_('App ').$id.$_(' is disabled by it\'s maintainer'));
			return $response->withRedirect($this->router->pathFor('apps.list'));
		}
		$this->menu->breadcrumb = array(
			array('name' => 'Apps', 'icon' => 'fa fa-rocket', 'url' => $this->router->pathFor('apps.list')),
			array('name' => $a['name'], 'url' => $this->router->pathFor('apps.byId', array('id'=> $id)))
		);
 		return $this->view->render($response, 'app.twig', [
			'a'		=> $a,
			'likes'		=> $this->getLikes($id),
			'offshot'	=> $this->getScreenShots($id),
			'comshot'	=> $this->getCommunityScreenShots($id),
			'vers'		=> $this->getVersionHistory($id),
			'comments'	=> $this->getComments($id)
 		]);
	}

	public function commentPost (Request $request, Response $response) {
		$_	= $this->trans;
		$this->auth->assertAuth($request, $response);
		$id = $request->getAttribute('id');
		$a  = $this->getApp($id);
		if (!is_array($a)) {
			$this->flash->addMessage('error', $_('No app ').$id.$_(' found'));
			return $response->withRedirect($this->router->pathFor('apps.list'));
		}
		$this->addComment($id, $request->getParam('comment'));
		$this->flash->addMessage('info', $_('Comment added'));
		return $response->withRedirect($this->router->pathFor('apps.byId', array('id'=> $id)));
	}

	public function appLikeGet (Request $request, Response $response) {
		$_	= $this->trans;
		$this->auth->assertAuth($request, $response);
		$id = $request->getAttribute('id');
		$a  = $this->getApp($id);
		if (!is_array($a)) {
			$this->flash->addMessage('error', $_('No app ').$id.$_(' found'));
			return $response->withRedirect($this->router->pathFor('apps.list'));
		}
		if ($this->toggleLike($id)) {
			$this->flash->addMessage('info', $_('Like added'));
		} else {
			$this->flash->addMessage('info', $_('Like removed'));
		}
		return $response->withRedirect($this->router->pathFor('apps.byId', array('id'=> $id)));
	}

	public function appEditPage (Request $request, Response $response) {
		$_	= $this->trans;
		$id = $request->getAttribute('id');
		$a  = $this->getApp($id);
		if (!is_array($a)) {
			$this->flash->addMessage('error', $_('No app ').$id.$_(' found'));
			return $response->withRedirect($this->router->pathFor('apps.list'));
		}
		$this->menu->breadcrumb = array(
			array('name' => 'Apps', 'icon' => 'fa fa-rocket', 'url' => $this->router->pathFor('apps.list')),
			array('name' => $a['name'], 'url' => $this->router->pathFor('apps.byId', array('id'=> $id))),
			array('name' => 'edit', 'icon' => 'fa fa-pencil', 'url' => $this->router->pathFor('apps.edit', array('id'=> $id)))
		);
 		return $this->view->render($response, 'appEdit.twig', [
			'a'	 => $a
 		]);
	}

	public function enablePost (Request $request, Response $response) {
		$_	= $this->trans;
		$this->auth->assertAuth($request, $response);
		$id = $request->getAttribute('id');
		$a  = $this->getApp($id);
		if (!is_array($a)) {
			$this->flash->addMessage('error', $_('No app ').$id.$_(' found'));
			return $response->withRedirect($this->router->pathFor('apps.list'));
		}
		if(!$this->isPackageMaintainer($a['dbp_id'])) {
			$this->flash->addMessage('error', $_("You're not a maintainer"));
			return $response->withRedirect($this->router->pathFor('apps.byId', array('id'=> $id)));
		}
		$this->toggleEnable($a);
		$this->flash->addMessage('info', $_("App status changed"));
		return $response->withRedirect($this->router->pathFor('apps.edit', array('id'=> $id)));
	}

	public function enableGet (Request $request, Response $response) {
		$_	= $this->trans;
		$this->auth->assertAuth($request, $response);
		$id = $request->getAttribute('id');
		$a  = $this->getApp($id);
		if (!is_array($a)) {
			$this->flash->addMessage('error', $_('No app ').$id.$_(' found'));
			return $response->withRedirect($this->router->pathFor('apps.list'));
		}
		if(!$this->isPackageMaintainer($a['dbp_id'])) {
			$this->flash->addMessage('error', $_("You're not a maintainer"));
			return $response->withRedirect($this->router->pathFor('apps.byId', array('id'=> $id)));
		}
		$this->toggleEnable($a);
		$this->flash->addMessage('info', $_("App enabled"));
		return $response->withRedirect($this->router->pathFor('packages.edit', array('str'=> $a['dbp_str_id'])));
	}

	public function descriptionPost (Request $request, Response $response) {
		$_	= $this->trans;
		$this->auth->assertAuth($request, $response);
		$id = $request->getAttribute('id');
		$a  = $this->getApp($id);
		if (!is_array($a)) {
			$this->flash->addMessage('error', $_('No app ').$id.$_(' found'));
			return $response->withRedirect($this->router->pathFor('apps.list'));
		}
		if(!$this->isPackageMaintainer($a['dbp_id'])) {
			$this->flash->addMessage('error', $_("You're not a maintainer"));
			return $response->withRedirect($this->router->pathFor('apps.byId', array('id'=> $id)));
		}
		$this->updateDesc($id, $request->getParam('desc'));
		$this->flash->addMessage('info', $_("Description updated"));
		return $response->withRedirect($this->router->pathFor('apps.edit', array('id'=> $id)));
	}

	public function screenshotPost (Request $request, Response $response) {
		$_	= $this->trans;
		$this->auth->assertAuth($request, $response);
		$id	= $request->getAttribute('id');
		$a	= $this->getApp($id);
		$date	= new DateTime();
		$ts	= $date->getTimestamp();
		$files	= $request->getUploadedFiles();
		$newfile= $files['screenshot'];
		$filename = "$ts-".str_replace(' ', '_', $newfile->getClientFilename());
		
		if (!is_array($a)) {
			$this->flash->addMessage('error', $_('No app ').$id.$_(' found'));
			return $response->withRedirect($this->router->pathFor('apps.list'));
		}
		if (empty($newfile))
			$this->flash->addMessage('error', 'No File uploaded');
		else if ($newfile->getError() === UPLOAD_ERR_OK) {
			$path = realpath(__DIR__.'/../public/pics').DIRECTORY_SEPARATOR.$a['dbp_str_id'].DIRECTORY_SEPARATOR;
			$webp = '/pics/'.$a['dbp_str_id'].'/'.$filename;
			if(!is_dir($path))
				mkdir($path);
			$newfile->moveTo($path.$filename);

			$finfo = new \finfo(FILEINFO_MIME);
			$mimetype = $finfo->file($path.$filename);
			$mimetypeParts = preg_split('/\s*[;,]\s*/', $mimetype);
			$mimetype = strtolower($mimetypeParts[0]);
			if (substr($mimetype,0,5) != "image") {
				$this->flash->addMessage('error', $mimetype.$_(' is not a supported type'));
				return $response->withRedirect($this->router->pathFor('apps.byId', array('id'=> $a['id'])));
			}

			$this->addScreenshot($a, $ts, $webp);
			$this->flash->addMessage('info', $_('Screenshot added'));
			return $response->withRedirect($this->router->pathFor('apps.byId', array('id'=> $a['id'])));
		} else
			$this->flash->addMessage('error', $_('Upload Failed'));
 		return $response->withRedirect($this->router->pathFor('apps.byId', array('id'=> $a['id'])));
	}
}

?>
