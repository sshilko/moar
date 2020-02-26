---
layout: page
title: About
id: about
permalink: /about/
---

I'am
<ul>
    <li>Senior Backend Engineer</li>
    <li>A Hardware nerd</li>
    <li>A (in past) old-school gamer</li>
</ul>

<a id="gf" href="https://github.com/sshilko/followers" title="Go to sshilko GitHub followers page" target="_blank"></a>
<br/>
<a id="gfr" href="https://github.com/sshilko/repositories" title="Go to sshilko GitHub repositories page" target="_blank"></a>
<br/>

<script type="text/javascript">
function JSONP( url, callback ) {
    var id = ( 'jsonp' + Math.random() * new Date() ).replace('.', '');
    var script = document.createElement('script');
    script.src = url.replace( 'callback=?', 'callback=' + id );
    document.body.appendChild( script );
    window[ id ] = function( data ) {
        if (callback) {
            callback( data );
        }
    };
}
JSONP( 'https://api.github.com/users/sshilko?callback=?', function( response ) {
        var data = response.data;
        if (data.followers > 0) {
            document.getElementById("gf").innerHTML = data.followers+' GitHub Followers';
        }
        if (data.public_repos > 0) {
            document.getElementById("gfr").innerHTML = data.public_repos+' GitHub Repos';
        }
});
</script>

### Feedback


<script type="text/javascript">
document.write('<a target="_blank" href="mailto:' + ('contact@' + 'sshilko.com') + '">by email</a>');
</script>
 
