<p>
    لطفا پست تایپ و طبقه بندی را انتخاب نمایید:
</p>

<p>پست تایپ</p>
<p>
    <select name="post_type">
        <?php
        $post_types = get_post_types([], 'objects');
        foreach ($post_types as $post_type) {
            ?>
            <option value="<?php echo $post_type->name; ?>"><?php echo $post_type->labels->singular_name; ?> [<?php echo $post_type->name; ?>]</option>
            <?php
        }
        ?>
    </select>
</p>

<p>طبقه بندی</p>
<p>
    <select name="taxonomy">
        <?php
        $taxonomies = get_taxonomies( [], 'objects' );
        foreach ($taxonomies as $taxonomy) {
            ?>
            <option value="<?php echo $taxonomy->name; ?>"><?php echo $taxonomy->labels->name; ?> [<?php echo $taxonomy->name; ?>]</option>
            <?php
        }
        ?>
    </select>
</p>