<?php
/**
 * The brand as the Cosmic Loop landing draws it: the three-circle mark, then
 * the site name with its first word bold and the rest light.
 *
 * @var string $href
 * @var string $class  extra classes on the link
 */
$brand = landing_brand(site_name());
$class ??= '';
?>
<a class="brand<?= $class !== '' ? ' ' . e($class) : '' ?>" href="<?= e($href) ?>"><span class="brand__mark" aria-hidden="true"><i></i><i></i><i></i></span><span><?= e($brand['name']) ?><span class="brand__light"><?= e($brand['light']) ?></span></span></a>
