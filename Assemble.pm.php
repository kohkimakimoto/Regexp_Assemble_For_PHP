<?php
/*
perl の regexp::Assemble を PHP に移植しています。
まだまだ移植中です。動きません。

perl のルーチンと1行づつ対訳をやっています。
間違っている所、たくさんあると思うので、手助けをお願いします。

*/

//sub _re_sort {
function _re_sort( $a, $b) {
//    return length $b <=> length $a || $a cmp $b
    $_temp_len_a = count($a);
    $_temp_len_b = count($b);
    if ( $_temp_len_b > $_temp_len_a ) {
        return 1;
    }
    else if ( $_temp_len_b < $_temp_len_a ) {
        return -1;
    }
    if (!is_array($a) && !is_array($b)) {
        return $a  < $b ? 1 : ($a  > $b ? 1 : 0);
    }

    // $a == $b
    return strcmp( 
          is_array($a) ? join('|' , $a) : $a 
          ,
          is_array($b) ? join('|' , $b) : $b 
    );
//}
}

//最後の添字を取得する.
function perl_lastindex(array $array)
{
    $p = array_keys($array);
    return array_pop($p);
}

//perl の push をエミュレーションする.
function perl_push(array $array , $target)
{
    if ( is_array($target) ){
       return array_merge($array , $target );
    }
    else {
       $array[] = $target;
       return $array;
    }
}

//perlのgrepに相当する関数
//array_filter って array_map とコールバックが逆なんで統一しておく。めんどいから。
function perl_grep($function ,array $array) {
    if ( ! is_array($array) )
    {
    	return [];
    }
    return array_filter($array , $function);
}

//perl の sort phpのsort関数はC言語と同じ自己破壊のクソ仕様なんで・・・
function perl_sort($function , $array = NULL)
{
    if ($array === NULL) {
       //arrayが省略された場合、 第一引数が arrayになる。
       sort($function);
       return $function;
    }
    else { 
       usort($array , $function);
       return $array;
    }
}

//perl の配列/Array定義のエミュレーション.
function perl_array($a,$b,$c = NULL){
    $r = [];
    $r = perl_push($r , $a);
    $r = perl_push($r , $b);
    if ( $c !== NULL) {
        $r = perl_push($r , $c);
    }
    return $r;
}

/*
# Regexp::Assemple.pm
#
# Copyright (c) 2004-2011 David Landgren
# All rights reserved
*/
//package Regexp::Assemble;
class Regexp_Assemble{

var $path = [];
var $debug = 255;
var $indent = 0;
var $lex = 0;

var $track = 0;
var $mutable = 0;
var $reduce = 1;
var $lookahead = 0;
var $unroll_plus = 0;
var $anchor_word_begin = 0;
var $anchor_line_begin = 0;
var $anchor_string_begin = 0;
var $anchor_word_end = 0;
var $anchor_line_end = 0;
var $anchor_string_end = 0;
var $anchor_string_end_absolute = 0;
var $flags = '';
var $fold_meta_pairs = 0;
var $dup_warn = 0;

//use vars qw/$VERSION $have_Storable $Current_Lexer $Default_Lexer $Single_Char $Always_Fail/;
//$VERSION = '0.35';
var $VERSION = '0.35';

/*
=head1 NAME

Regexp::Assemble - Assemble multiple Regular Expressions into a single RE

=head1 VERSION

This document describes version 0.35 of Regexp::Assemble, released
2011-04-07.

=head1 SYNOPSIS

  use Regexp::Assemble;
  
  my $ra = Regexp::Assemble->new;
  $ra->add( 'ab+c' );
  $ra->add( 'ab+-' );
  $ra->add( 'a\w\d+' );
  $ra->add( 'a\d+' );
  print $ra->re; # prints a(?:\w?\d+|b+[-c])

=head1 DESCRIPTION

Regexp::Assemble takes an arbitrary number of regular expressions
and assembles them into a single regular expression (or RE) that
matches all that the individual REs match.

As a result, instead of having a large list of expressions to loop
over, a target string only needs to be tested against one expression.
This is interesting when you have several thousand patterns to deal
with. Serious effort is made to produce the smallest pattern possible.

It is also possible to track the original patterns, so that you can
determine which, among the source patterns that form the assembled
pattern, was the one that caused the match to occur.

You should realise that large numbers of alternations are processed
in perl's regular expression engine in O(n) time, not O(1). If you
are still having performance problems, you should look at using a
trie. Note that Perl's own regular expression engine will implement
trie optimisations in perl 5.10 (they are already available in
perl 5.9.3 if you want to try them out). C<Regexp::Assemble> will
do the right thing when it knows it's running on a a trie'd perl.
(At least in some version after this one).

Some more examples of usage appear in the accompanying README. If
that file isn't easy to access locally, you can find it on a web
repository such as
L<http://search.cpan.org/dist/Regexp-Assemble/README> or
L<http://cpan.uwinnipeg.ca/htdocs/Regexp-Assemble/README.html>.

=cut
*/

//use strict;

//use constant DEBUG_ADD  => 1;
//use constant DEBUG_TAIL => 2;
//use constant DEBUG_LEX  => 4;
//use constant DEBUG_TIME => 8;
var $DEBUG_ADD  = 1;
var $DEBUG_TAIL = 2;
var $DEBUG_LEX = 4;
var $DEBUG_TIME = 8;

//# The following patterns were generated with eg/naive
//$Default_Lexer = qr/(?![[(\\]).(?:[*+?]\??|\{\d+(?:,\d*)?\}\??)?|\\(?:[bABCEGLQUXZ]|[lu].|(?:[^\w]|[aefnrtdDwWsS]|c.|0\d{2}|x(?:[\da-fA-F]{2}|{[\da-fA-F]{4}})|N\{\w+\}|[Pp](?:\{\w+\}|.))(?:[*+?]\??|\{\d+(?:,\d*)?\}\??)?)|\[.*?(?<!\\)\](?:[*+?]\??|\{\d+(?:,\d*)?\}\??)?|\(.*?(?<!\\)\)(?:[*+?]\??|\{\d+(?:,\d*)?\}\??)?/; # ]) restore equilibrium
var $Default_Lexer = '(?![[(\\\\]).(?:[*+?]\??|\{\d+(?:,\d*)?\}\??)?|\\\\(?:[bABCEGLQUXZ]|[lu].|(?:[^\w]|[aefnrtdDwWsS]|c.|0\d{2}|x(?:[\da-fA-F]{2}|{[\da-fA-F]{4}})|N\{\w+\}|[Pp](?:\{\w+\}|.))(?:[*+?]\??|\{\d+(?:,\d*)?\}\??)?)|\[.*?(?<!\\\\)\](?:[*+?]\??|\{\d+(?:,\d*)?\}\??)?|\(.*?(?<!\\\\)\)(?:[*+?]\??|\{\d+(?:,\d*)?\}\??)?'; //# ]) restore equilibrium

//$Single_Char   = qr/^(?:\\(?:[aefnrtdDwWsS]|c.|[^\w\/{|}-]|0\d{2}|x(?:[\da-fA-F]{2}|{[\da-fA-F]{4}}))|[^\$^])$/;
//var $Single_Char   = '(?:\\(?:[aefnrtdDwWsS]|c.|[^\w\/{|}-]|0\d{2}|x(?:[\da-fA-F]{2}|{[\da-fA-F]{4}}))|[^\$^])';
var $Single_Char   = '(?:\\\\(?:[aefnrtdDwWsS]|c.|[^\w\/{|}-]|0\d{2}|x(?:[\da-fA-F]{2}|{[\da-fA-F]{4}}))|[^\$^])';

//# the pattern to return when nothing has been added (and thus not match anything)
//$Always_Fail = "^\\b\0";
var $Always_Fail = '^\\\\b\0';

/*
=head1 METHODS

=over 8

=item new

Creates a new C<Regexp::Assemble> object. The following optional
key/value parameters may be employed. All keys have a corresponding
method that can be used to change the behaviour later on. As a
general rule, especially if you're just starting out, you don't
have to bother with any of these.

B<anchor_*>, a family of optional attributes that allow anchors
(C<^>, C<\b>, C<\Z>...) to be added to the resulting pattern.

B<flags>, sets the C<imsx> flags to add to the assembled regular
expression.  Warning: no error checking is done, you should ensure
that the flags you pass are understood by the version of Perl you
are using. B<modifiers> exists as an alias, for users familiar
with L<Regexp::List>.

B<chomp>, controls whether the pattern should be chomped before being
lexed. Handy if you are reading patterns from a file. By default, 
C<chomp>ing is performed (this behaviour changed as of version 0.24,
prior versions did not chomp automatically).
See also the C<file> attribute and the C<add_file> method.

B<file>, slurp the contents of the specified file and add them
to the assembly. Multiple files may be processed by using a list.

  my $r = Regexp::Assemble->new(file => 're.list');

  my $r = Regexp::Assemble->new(file => ['re.1', 're.2']);

If you really don't want chomping to occur, you will have to set
the C<chomp> attribute to 0 (zero). You may also want to look at
the C<input_record_separator> attribute, as well.

B<input_record_separator>, controls what constitutes a record
separator when using the C<file> attribute or the C<add_file>
method. May be abbreviated to B<rs>. See the C<$/> variable in
L<perlvar>.

B<lookahead>, controls whether the pattern should contain zero-width
lookahead assertions (For instance: (?=[abc])(?:bob|alice|charles).
This is not activated by default, because in many circumstances the
cost of processing the assertion itself outweighs the benefit of
its faculty for short-circuiting a match that will fail. This is
sensitive to the probability of a match succeeding, so if you're
worried about performance you'll have to benchmark a sample population
of targets to see which way the benefits lie.

B<track>, controls whether you want know which of the initial
patterns was the one that matched. See the C<matched> method for
more details. Note for version 5.8 of Perl and below, in this mode
of operation YOU SHOULD BE AWARE OF THE SECURITY IMPLICATIONS that
this entails. Perl 5.10 does not suffer from any such restriction.

B<indent>, the number of spaces used to indent nested grouping of
a pattern. Use this to produce a pretty-printed pattern. See the
C<as_string> method for a more detailed explanation.

B<pre_filter>, allows you to add a callback to enable sanity checks
on the pattern being loaded. This callback is triggered before the
pattern is split apart by the lexer. In other words, it operates
on the entire pattern. If you are loading patterns from a file,
this would be an appropriate place to remove comments.

B<filter>, allows you to add a callback to enable sanity checks on
the pattern being loaded. This callback is triggered after the
pattern has been split apart by the lexer.

B<unroll_plus>, controls whether to unroll, for example, C<x+> into
C<x>, C<x*>, which may allow additional reductions in the
resulting assembled pattern.

B<reduce>, controls whether tail reduction occurs or not. If set,
patterns like C<a(?:bc+d|ec+d)> will be reduced to C<a[be]c+d>.
That is, the end of the pattern in each part of the b... and d...
alternations is identical, and hence is hoisted out of the alternation
and placed after it. On by default. Turn it off if you're really
pressed for short assembly times.

B<lex>, specifies the pattern used to lex the input lines into
tokens. You could replace the default pattern by a more sophisticated
version that matches arbitrarily nested parentheses, for example.

B<debug>, controls whether copious amounts of output is produced
during the loading stage or the reducing stage of assembly.

  my $ra = Regexp::Assemble->new;
  my $rb = Regexp::Assemble->new( chomp => 1, debug => 3 );

B<mutable>, controls whether new patterns can be added to the object
after the assembled pattern is generated. DEPRECATED.

This method/attribute will be removed in a future release. It doesn't
really serve any purpose, and may be more effectively replaced by
cloning an existing C<Regexp::Assemble> object and spinning out a
pattern from that instead.

A more detailed explanation of these attributes follows.

=cut
*/

//sub new {
//    my $class = shift;
//    my %args  = @_;
function __constructor($args) {
//    my $anc;
    $anc = 0;
//    for $anc (qw(word line string)) {
    foreach( array("word","line","string") as $anc){
//        if (exists $args{"anchor_$anc"}) {
          if ( isset($args["anchor_$anc"]) ){
//            my $val = delete $args{"anchor_$anc"};
            $val = $args["anchor_$anc"];
            unset($args["anchor_$anc"]);
//            for my $anchor ("anchor_${anc}_begin", "anchor_${anc}_end") {
              foreach(array("anchor_${anc}_begin", "anchor_${anc}_end") as $anchor){
//                $args{$anchor} = $val unless exists $args{$anchor};
                  if (!isset($args[$anchor])) $args[$anchor] = $val;
//            }
              }
//        }
          }
//  }
    }

//    # anchor_string_absolute sets anchor_string_begin and anchor_string_end_absolute
//    if (exists $args{anchor_string_absolute}) {
    if (isset($args['anchor_string_absolute'])){
//        my $val = delete $args{anchor_string_absolute};
        $val = $args['anchor_string_absolute'];
        unset($args['anchor_string_absolute']);
//        for my $anchor (qw(anchor_string_begin anchor_string_end_absolute)) {
        foreach( array('anchor_string_begin', 'anchor_string_end_absolute') as $anchor){
//            $args{$anchor} = $val unless exists $args{$anchor};
           if (!isset($args[$anchor])) $args[$anchor] = $val;
//      }
        }
//   }
    }

/*
    exists $args{$_} or $args{$_} = 0 for qw(
        anchor_word_begin
        anchor_word_end
        anchor_line_begin
        anchor_line_end
        anchor_string_begin
        anchor_string_end
        anchor_string_end_absolute
        debug
        dup_warn
        indent
        lookahead
        mutable
        track
        unroll_plus
    );
*/
    foreach( array(   'anchor_word_begin'
                     ,'anchor_word_end'
                     ,'anchor_line_begin'
                     ,'anchor_line_end'
                     ,'anchor_string_begin'
                     ,'anchor_string_end'
                     ,'anchor_string_end_absolute'
                     ,'debug'
                     ,'dup_warn'
                     ,'indent'
                     ,'lookahead'
                     ,'mutable'
                     ,'track'
                     ,'unroll_plus'
                     ) as $_){
         if ( !isset($args[$_]) )  $args[$_] = 0;
    }

/*
    exists $args{$_} or $args{$_} = 1 for qw(
        fold_meta_pairs
        reduce
        chomp
    );
*/
    foreach( array(     'fold_meta_pairs'
          ,'reduce'
          ,'chomp'
          )
        as $_)
    {
        if ( !isset($args[$_]) ) $args[$_] = 1;
    }

//    @args{qw(re str path)} = (undef, undef, []);
    $args['re'] = NULL;
    $args['str'] = NULL;
    $args['path'] = array();

//    $args{flags} ||= delete $args{modifiers} || '';
    if (!isset($args['flags'])){
        unset($args['modifiers']);
        $args['flags'] = '';
    }
//    $args{lex}     = $Current_Lexer if defined $Current_Lexer;
    if ( isset($this->Current_Lexer) ){
        $args['lex']     = $this->Current_Lexer;
    }

//    my $self = bless \%args, $class;    //class

//    if ($self->_debug(DEBUG_TIME)) {
//        $self->_init_time_func();
//        $self->{_begin_time} = $self->{_time_func}->();
//    }
//skip debug.


//    $self->{input_record_separator} = delete $self->{rs}
//        if exists $self->{rs};
    if ( isset($this->rs) ){
        $this->input_record_separator = $this->rs;
        unset($this->rs);
    }

//    exists $self->{file} and $self->add_file($self->{file});
    if (isset($this->file)){
        $this->add_file($this->file);
    }

//    return $self;
}

/* //skip debug
sub _init_time_func {
    my $self = shift;
    return if exists $self->{_time_func};
    if (isset($this->_time_func))
    {
        return $this->_time_func();
    }

    # attempt to improve accuracy
    if (!defined($self->{_use_time_hires})) {
        eval {require Time::HiRes};
        $self->{_use_time_hires} = $@;
    }
    $self->{_time_func} = length($self->{_use_time_hires}) > 0
        ? sub { time }
        : \&Time::HiRes::time
    ;
}
*/

/*
=item clone

Clones the contents of a Regexp::Assemble object and creates a new
object (in other words it performs a deep copy).

If the Storable module is installed, its dclone method will be used,
otherwise the cloning will be performed using a pure perl approach.

You can use this method to take a snapshot of the patterns that have
been added so far to an object, and generate an assembly from the
clone. Additional patterns may to be added to the original object
afterwards.

  my $re = $main->clone->re();
  $main->add( 'another-pattern-\\d+' );

=cut
*/

/* skip phpの clone使え!phpの clone使え!
sub clone {
    my $self = shift;
    my $clone;
    my @attr = grep {$_ ne 'path'} keys %$self;
    @{$clone}{@attr} = @{$self}{@attr};
    $clone->{path}   = _path_clone($self->_path);
    bless $clone, ref($self);
}
*/
/*
=item add(LIST)

Takes a string, breaks it apart into a set of tokens (respecting
meta characters) and inserts the resulting list into the C<R::A>
object. It uses a naive regular expression to lex the string
that may be fooled complex expressions (specifically, it will
fail to lex nested parenthetical expressions such as
C<ab(cd(ef)?gh)ij> correctly). If this is the case, the end of
the string will not be tokenised correctly and returned as one
long string.

On the one hand, this may indicate that the patterns you are
trying to feed the C<R::A> object are too complex. Simpler
patterns might allow the algorithm to work more effectively and
perform more reductions in the resulting pattern.

On the other hand, you can supply your own pattern to perform the
lexing if you need. The test suite contains an example of a lexer
pattern that will match one level of nested parentheses.

Note that there is an internal optimisation that will bypass a
much of the lexing process. If a string contains no C<\>
(backslash), C<[> (open square bracket), C<(> (open paren),
C<?> (question mark), C<+> (plus), C<*> (star) or C<{> (open
curly), a character split will be performed directly.

A list of strings may be supplied, thus you can pass it a file
handle of a file opened for reading:

    $re->add( '\d+-\d+-\d+-\d+\.example\.com' );
    $re->add( <IN> );

If the file is very large, it may be more efficient to use a
C<while> loop, to read the file line-by-line:

    $re->add($_) while <IN>;

The C<add> method will chomp the lines automatically. If you
do not want this to occur (you want to keep the record
separator), then disable C<chomp>ing.

    $re->chomp(0);
    $re->add($_) while <IN>;

This method is chainable.

=cut
*/
//sub _fastlex {
//    my $self   = shift;
//    my $record = shift;
function _fastlex($record){

//    my $len    = 0;
    $len = 0;
//    my @path   = ();
    $path = array();
//    my $case   = '';
    $case = '';
//    my $qm     = '';
    $qm = '';

//    my $debug       = $self->{debug} & DEBUG_LEX;
    $debug       = $this->debug & $this->DEBUG_LEX;
//    my $unroll_plus = $self->{unroll_plus};
    $unroll_plus = $this->unroll_plus;

//    my $token;
    $token = NULL;
//    my $qualifier;
    $qualifier = NULL;

//    $debug and print "# _lex <$record>\n";
    if ( $debug ) { echo "# _lex <$record>\n"; } 

//    my $modifier        = q{(?:[*+?]\\??|\\{(?:\\d+(?:,\d*)?|,\d+)\\}\\??)?};
    $modifier        = '(?:[*+?]\\\\??|\\\\{(?:\\\\d+(?:,\d*)?|,\d+)\\\\}\\\\??)?';
//    my $class_matcher   = qr/\[(?:\[:[a-z]+:\]|\\?.)*?\]/;
    $class_matcher   = "\[(?:\[:[a-z]+:\]|\\\\?.)*?\]";
//    my $paren_matcher   = qr/\(.*?(?<!\\)\)$modifier/;
    $paren_matcher   = "\(.*?(?<!\\\\)\){$modifier}";
//    my $misc_matcher    = qr/(?:(c)(.)|(0)(\d{2}))($modifier)/;
    $misc_matcher    = "(?:(c)(.)|(0)(\d{2}))({$modifier})";
//    my $regular_matcher = qr/([^\\[(])($modifier)/;
    $regular_matcher = "([^\\\\[(])({$modifier})";
//    my $qm_matcher      = qr/(\\?.)/;
    $qm_matcher      = '(\\\\?.)';

//    my $matcher = $regular_matcher;
    $matcher = $regular_matcher;

    $stripRecord = $record;
    $pregNum = array(); // $1 $2 などの置き換えに使う.

//    {
      while(1){
//        if ($record =~ /\G$matcher/gc) {
          if ( preg_match("/^{$matcher}/u",$stripRecord,$pregNum)  ){
             $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

//           # neither a \\ nor [ nor ( followed by a modifer
//           if ($1 eq '\\E') {
             if ($pregNum[1] ==  '\\E'){
//                $debug and print "#   E\n";
                  if ($debug){  echo "#   E\n";   }
//                $case = $qm = '';
                  $case = '' ; $qm = '';
//                $matcher = $regular_matcher;
                  $matcher = $regular_matcher;
//                redo;
                  continue;
//            }
            }
//            elsif ($qm and ($1 eq '\\L' or $1 eq '\\U')) {
            else if ($qm && ($pregNum[1] == '\\L' || $pregNum[1] == '\\U')){
//                $debug and print "#  ignore \\L, \\U\n";
                  if ($debug){  echo "#  ignore \\L, \\U\n";   }
//                redo;
                  continue;
//            }
            }
//            $token = $1;
            $token = $pregNum[1];
//            $qualifier = defined $2 ? $2 : '';
            $qualifier = isset($pregNum[2]) ? $pregNum[2] : '';
//            $debug and print "#  token <$token> <$qualifier>\n";
            if ($debug){    echo "#  token <$token> <$qualifier>\n";    }

//            if ($qm) {
            if ($qm){
//                $token = quotemeta($token);
                $token = quotemeta($token);
//                $token =~ s/^\\([^\w$()*+.?@\[\\\]^|{}\/])$/$1/;
                $token = preg_replace('/^\\\\([^\w$()*+.?@\[\\\\\]^|{}\/])$/u' , '$1' , $token);
//            }
            }
//            else {
            else {
//                $token =~ s{\A([][{}*+?@\\/])\Z}{\\$1};
                  $token = preg_replace('#\A([][{}*+?@\\\\/])\Z#u' , '\\$1' , $token);
//            }
            }

//            if ($unroll_plus and $qualifier =~ s/\A\+(\?)?\Z/*/) {
            if ($unroll_plus && preg_match('/\A\+(\?)?\Z/u',$qualifier,$pregNum)  ){
                $qualifier = preg_replace('/\A\+(\?)?\Z/u','*' , $qualifier); //$qualifier =~ を一度にできないため
                
//                $1 and $qualifier .= $1;
                if ( isset($pregNum[1]) ) $qualifier .= $pregNum[1];

//                $debug and print " unroll <$token><$token><$qualifier>\n";
                if ($debug){    print " unroll <$token><$token><$qualifier>\n";    }

//                $case and $token = $case eq 'L' ? lc($token) : uc($token);
                if ($case) $token = ($case == 'L' ? strtolower($token) : strtoupper($token));

//                push @path, $token, "$token$qualifier";
                $path[] = $token;
                $path[] = "{$token}{$qualifier}";
//            }
            }
//            else {
            else {
//                $debug and print " clean <$token>\n";
                if ($debug){ print " clean <$token>\n"; }

//                push @path,
//                      $case eq 'L' ? lc($token).$qualifier
//                    : $case eq 'U' ? uc($token).$qualifier
//                    :                   $token.$qualifier
//                    ;
                  $path[] = ( $case == 'L' ? strtolower($token).$qualifier 
                          : ( $case == 'U' ? strtoupper($token).$qualifier 
                          : $token.$qualifier
                          ) 
                  );
//            }
            }
//            redo;
            continue;
//        }
        }
//        elsif ($record =~ /\G\\/gc) {
        else if (preg_match('/\\\\/u',$stripRecord , $pregNum )){
            $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

//            $debug and print "#  backslash\n";
            if ($debug){ echo "#  backslash\n"; }

//            # backslash
//            if ($record =~ /\G([sdwSDW])($modifier)/gc) {
            if (preg_match("/^([sdwSDW])($modifier)/u",$stripRecord,$pregNum)){
                $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

//                ($token, $qualifier) = ($1, $2);
                $token = $pregNum[1];
                $qualifier = $pregNum[2];

//                $debug and print "#   meta <$token> <$qualifier>\n";
                if ($debug){    echo "#   meta <$token> <$qualifier>\n";    }

//                push @path, ($unroll_plus and $qualifier =~ s/\A\+(\?)?\Z/*/)
//                    ? ("\\$token", "\\$token$qualifier" . (defined $1 ? $1 : ''))
//                    : "\\$token$qualifier";
                if ($unroll_plus && preg_match("/^\A\+(\?)?\Z/u",$qualifier,$pregNum) ){
                     $qualifier = preg_replace('/^/\A\+(\?)?\Z/u','*' , $qualifier);
                     $path[] = "\\$token";
                     $path[] = "\\$token$qualifier";
                     $path[] = isset($pregNum[1]) ? $pregNum[1] : '';
                }
                else{
                     $path[] = "\\$token$qualifier";
                }
//            }
            }
//            elsif ($record =~ /\Gx([\da-fA-F]{2})($modifier)/gc) {
            else if (preg_match("/^x([\da-fA-F]{2})($modifier)/u",$stripRecord,$pregNum)){
                $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

//                $debug and print "#   x $1\n";
                if ($debug){ echo "#   x $1\n"; }

//                $token = quotemeta(chr(hex($1)));
                $token = quotemeta(chr(hex($pregNum[1])));

//                $qualifier = $2;
                $qualifier = $pregNum[2];

//                $debug and print "#  cooked <$token>\n";
                if ($debug){    echo "#  cooked <$token>\n";    }

//                $token =~ s/^\\([^\w$()*+.?\[\\\]^|{\/])$/$1/; # } balance
                $token = preg_replace("/^\\\\([^\w$()*+.?\[\\\\\]^|{\/])$/u",'$1', $token);    // } balance

//                $debug and print "#   giving <$token>\n";
                if ($debug){    echo "#   giving <$token>\n";    }

//                push @path, ($unroll_plus and $qualifier =~ s/\A\+(\?)?\Z/*/)
//                    ? ($token, "$token$qualifier" . (defined $1 ? $1 : ''))
//                    : "$token$qualifier";
                if ($unroll_plus && preg_match("/^\A\+(\?)?\Z/u",$qualifier,$pregNum)){
                     $qualifier = preg_replace('/^/\A\+(\?)?\Z/u','*' , $qualifier);
                     $path[] = "\\$token";
                     $path[] = "\\$token$qualifier";
                     $path[] = isset($pregNum[1]) ? $pregNum[1] : '';
                }
                else{
                     $path[] = "\\$token$qualifier";
                }
//            }
            }
//            elsif ($record =~ /\GQ/gc) {
            else if (preg_match("/^Q/u" ,$stripRecord , $pregNum) ){
                $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

//                $debug and print "#   Q\n";
                if ($debug){    echo "#   Q\n";    }
//                $qm = 1;
                $qm = 1;
//                $matcher = $qm_matcher;
                $matcher = $qm_matcher;
//            }
            }
//            elsif ($record =~ /\G([LU])/gc) {
            else if (preg_match("/^([LU])/u" ,$stripRecord ,$pregNum ) ){
                $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

//                $debug and print "#   case $1\n";
                if ($debug){    echo "#   case $1\n";    }
//                $case = $1;
                $case = $pregNum[1];
//            }
            }
//            elsif ($record =~ /\GE/gc) {
            else if (preg_match("/^E/u" ,$stripRecord ,$pregNum)){
                $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

//                $debug and print "#   E\n";
                if ($debug){    echo "#   E\n";    }
//                $case = $qm = '';
                $case = ''; $qm = '';
//                $matcher = $regular_matcher;
                $matcher = $regular_matcher;
//            }
            }
//            elsif ($record =~ /\G([lu])(.)/gc) {
            else if (preg_match("/^([lu])(.)/u" ,$stripRecord ,$pregNum)){
                $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

//                $debug and print "#   case $1 to <$2>\n";
                if ($debug){    echo "#   case $1 to <$2>\n";    }
//                push @path, $1 eq 'l' ? lc($2) : uc($2);
                $path[] = $pregNum[1] == 'l' ? strtolower($pregNum[2]) : strtoupper($pregNum[2]);
//            }
            }
//            elsif (my @arg = grep {defined} $record =~ /\G$misc_matcher/gc) {
            else if ( preg_match("/^$misc_matcher/u",$stripRecord , $pregNum) ){
                $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

                $arg = perl_grep( function($_){ return $_ !== ''; }  , array_slice($pregNum , 1) );

//                if ($] < 5.007) {   //skip old version
//                    my $len = 0;
//                    $len += length($_) for @arg;
//                    $debug and print "#  pos ", pos($record), " fixup add $len\n";
//                    pos($record) = pos($record) + $len;
//                }
//                my $directive = shift @arg;
                $directive = array_shift($arg);
//                if ($directive eq 'c') {
                if ($directive == 'c'){
//                    $debug and print "#  ctrl <@arg>\n";
                    if ($debug){    echo "#  ctrl <@arg>\n";    }
//                    push @path, "\\c" . uc(shift @arg);
                    $path[] = "\\c";
                    $path[] = strtoupper(array_shift($arg));
//                }
                }
//                else { # elsif ($directive eq '0') {
                else{ // elsif ($directive eq '0') 
//                    $debug and print "#  octal <@arg>\n";
                    if ($debug){    echo "#  octal <@arg>\n";    }

//                    my $ascii = oct(shift @arg);
                    $ascii = decoct(array_shift($arg));

//                    push @path, ($ascii < 32)
//                        ? "\\c" . chr($ascii+64)
//                        : chr($ascii)
//                    ;
                      if ($ascii < 32){
                           $path[] = "\\c" . chr($ascii+64);
                      }
                      else{
                           $path[] = chr($ascii);
                      }
                }
//                $path[-1] .= join( '', @arg ); # if @arg;
                $path[-1] .= join( '', $arg ); // if @arg;
//                redo;
                continue;
            }
//            elsif ($record =~ /\G(.)/gc) {
            else if ( preg_match("/^(.)/u" , $stripRecord, $pregNum) ){
                $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

//                $token = $1;
                $token = $pregNum[1];
//                $token =~ s{[AZabefnrtz\[\]{}()\\\$*+.?@|/^]}{\\$token};
                $token = preg_replace("/[AZabefnrtz\[\]{}()\\\\\$*+.?@|\/^]/u","\\$token",$token);
//                $debug and print "#   meta <$token>\n";
                if ($debug){    echo "#   meta <$token>\n";    }
//                push @path, $token;
                $path[] = $token;
//            }
            }
//            else {
            else {
//                $debug and print "#   ignore char at ", pos($record), " of <$record>\n";
                if ($debug){    echo "#   ignore char at " . (strlen($record) - strlen($stripRecord)  ) . " of <$record>\n";    }
//            }
            }
//            redo;
            continue;
//        }
        }
//        elsif ($record =~ /\G($class_matcher)($modifier)/gc) {
        else if ( preg_match("/^($class_matcher)($modifier)/u",$stripRecord,$pregNum) ){
             $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

//            # [class] followed by a modifer
//            my $class     = $1;
            $class     = $pregNum[1];
//            my $qualifier = defined $2 ? $2 : '';
            $qualifier = isset($pregNum[2]) ? $pregNum[2] : '';

//            $debug and print "#  class begin <$class> <$qualifier>\n";
            if ($debug){    echo "#  class begin <$class> <$qualifier>\n";    }

//            if ($class =~ /\A\[\\?(.)]\Z/) {
            if (preg_match("/^\A\[\\\\?(.)]\Z/u" , $class , $pregNum ) ){
//                $class = quotemeta $1;
                $class = preg_quote($pregNum[1]);
//                $class =~ s{\A\\([!@%])\Z}{$1};
                $class = preg_replace("/#A\\\\([!@%])\Z#u",'$1',$class);
//                $debug and print "#  class unwrap $class\n";
                if ($debug){    echo "#  class unwrap $class\n";    }
//            }
            }
//            $debug and print "#  class end <$class> <$qualifier>\n";
            if ($debug)    {    echo "#  class end <$class> <$qualifier>\n"; }
//            push @path, ($unroll_plus and $qualifier =~ s/\A\+(\?)?\Z/*/)
//                ? ($class, "$class$qualifier" . (defined $1 ? $1 : ''))
//                : "$class$qualifier";
            if ($unroll_plus && preg_match("/^\A\+(\?)?\Z/u",$qualifier,$pregNum)){
                $qualifier = preg_replace("/^\A\+(\?)?\Z/u","/*/",$qualifier);
                $path[] = $class;
                $path[] = "$class$qualifier";
                $path[] = isset($pregNum[1]) ? $pregNum[1]  : '';
            }
            else{
                $path[] = "$class$qualifier";
            }
//            redo;
            continue;
//        }
        }
//        elsif ($record =~ /\G($paren_matcher)/gc) {
        else if (preg_match("/^({$paren_matcher})/u",$stripRecord , $pregNum) ){
             $stripRecord = substr($stripRecord , strlen($pregNum[0]) ); // \G なので削る

//            $debug and print "#  paren <$1>\n";
             if ($debug){    echo "#  paren <{$pregNum[1]}>\n";    }
//            # (paren) followed by a modifer
//            push @path, $1;
             $path[] = $pregNum[1];
//            redo;
             continue;
//        }
        }

//     }
        break;
     } //redo に対抗するための擬似ループ

    return $path;
}

//sub _lex {
//    my $self   = shift;
//    my $record = shift;
function _lex($record){
//    my $len    = 0;
    $len = 0;
//    my @path   = ();
    $path = array();
//    my $case   = '';
    $case   = '';
//    my $qm     = '';
    $qm   = '';
//    my $re     = defined $self->{lex} ? $self->{lex}
//        : defined $Current_Lexer ? $Current_Lexer
//        : $Default_Lexer;
    $re   = isset($this->lex) ? $this->lex : 
          (isset($this->Current_Lexer) ? $this->Current_Lexer : $this->Default_Lexer);

//    my $debug  = $self->{debug} & DEBUG_LEX;
    $debug       = $this->debug & $this->DEBUG_LEX;

//    $debug and print "# _lex <$record>\n";
    if ($debug){    echo "# _lex <$record>\n";    }

//    my ($token, $next_token, $diff, $token_len);
    $token = '';
    $next_token = '';
    $diff = '';
    $token_len = '';
    $pregNum = array();    //$1 とかのために使う.

//    while( $record =~ /($re)/g ) {
      while( preg_match("/($re)/u" , $record , $pregNum) ){
//        $token = $1;
        $token = $pregNum[1];
//        $token_len = length($token);
        $token_len = length($token);
//        $debug and print "# lexed <$token> len=$token_len\n";
        if ($debug){    echo "# lexed <$token> len=$token_len\n"; }
//        if( pos($record) - $len > $token_len ) {
        if ( pos($record) - $len > $token_len ){
//            $next_token = $token;
            $next_token = $token;
//            $token = substr( $record, $len, $diff = pos($record) - $len - $token_len );
            $token = substr( $record, $len, $diff = pos($record) - $len - $token_len );
//            $debug and print "#  recover <", substr( $record, $len, $diff ), "> as <$token>, save <$next_token>\n";
            if ($debug){ echo "#  recover <", substr( $record, $len, $diff ), "> as <$token>, save <$next_token>\n"; }
//            $len += $diff;
            $len += $diff;
//        }
        }
//        $len += $token_len;
        $len += $token_len;
//        TOKEN: {
        TOKEN: {  //php の goto がこんな所で役に立つとは・・・
//            if( substr( $token, 0, 1 ) eq '\\' ) {
            if ( substr( $token, 0, 1 ) == '\\' ){
//                if( $token =~ /^\\([ELQU])$/ ) {
                if ( preg_match("/^\\\\([ELQU])$/u" , $token , $pregNum) ){
//                    if( $1 eq 'E' ) {
                     if( $pregNum[1] == 'E' ) {
//                        $qm and $re = defined $self->{lex} ? $self->{lex}
//                            : defined $Current_Lexer ? $Current_Lexer
//                            : $Default_Lexer;
                        if ($qm) {
                             $re = isset($this->lex) ? $this->lex : $Default_Lexer;
                        }
//                        $case = $qm = '';
                        $case = ''; $qm = '';
//                    }
                    }
//                    elsif( $1 eq 'Q' ) {
                    else if( $pregNum[1] == 'Q' ) {
//                        $qm = $1;
                        $qm = $pregNum[1];
//                        # switch to a more precise lexer to quotemeta individual characters
//                        $re = qr/\\?./;
                        $re = "\\?.";
//                    }
                    }
//                    else {
                    else {
//                        $case = $1;
                        $case = $pregNum[1];
//                    }
                    }
//                    $debug and print "#  state change qm=<$qm> case=<$case>\n";
                    if ($debug){    echo "#  state change qm=<$qm> case=<$case>\n";    }
//                    goto NEXT_TOKEN;
                    goto NEXT_TOKEN;
//                }
                }
//                elsif( $token =~ /^\\([lu])(.)$/ ) {
                else if ( preg_match("/^\\\\([lu])(.)$/u",$token , $pregNum) ) {
//                    $debug and print "#  apply case=<$1> to <$2>\n";
                    if ($debug){    echo "#  apply case=<$1> to <$2>\n";    }
//                    push @path, $1 eq 'l' ? lc($2) : uc($2);
                    $path[] = $pregNum[1] == 'l' ? 
                    strtolower($pregNum[2]) : strtolower($pregNum[2]);
//                    goto NEXT_TOKEN;
                    goto NEXT_TOKEN;
//                }
                }
//                elsif( $token =~ /^\\x([\da-fA-F]{2})$/ ) {
                else if ( preg_match("/^\\\\x([\da-fA-F]{2})$/u",$token , $pregNum) ) {
//                    $token = quotemeta(chr(hex($1)));
                    $token = preg_quote(chr(dechex($pregNum[1])));
//                    $debug and print "#  cooked <$token>\n";
                    if ($debug){ echo "#  cooked <$token>\n"; }
//                    $token =~ s/^\\([^\w$()*+.?@\[\\\]^|{\/])$/$1/; # } balance
                    $token = preg_replace("/^\\\\([^\w$()*+.?@\[\\\\\]^|{\/])$/u","$1" , $token);
//                    $debug and print "#   giving <$token>\n";
                    if ($debug){ echo "#   giving <$token>\n"; }
//                }
                }
//                else {
                else {
//                    $token =~ s/^\\([^\w$()*+.?@\[\\\]^|{\/])$/$1/; # } balance
                    $token = preg_replace("/^\\\\([^\w$()*+.?@\[\\\\\]^|{\/])$/u","$1" , $token);
//                    $debug and print "#  backslashed <$token>\n";
                    if ($debug){ echo "#  backslashed <$token>\n"; }
//                }
                }
//            }
            }
//            else {
            else {
//                $case and $token = $case eq 'U' ? uc($token) : lc($token);
                if ($case){
                     $token = $case == 'U' ? strtoupper($token) : strtolower($token);
                }
//                $qm   and $token = quotemeta($token);
                if ($qm){
                     $token = preg_quote($token);
                }
//                $token = '\\/' if $token eq '/';
                if ($token == '/'){
                    $token = '\\/';
                }
            }
//            # undo quotemeta's brute-force escapades
//            $qm and $token =~ s/^\\([^\w$()*+.?@\[\\\]^|{}\/])$/$1/;
            if ($qm){
                $token = preg_replace('/^\\\\([^\w$()*+.?@\[\\\\\]^|{}\/])$/u','$1',$token);
            }
//            $debug and print "#   <$token> case=<$case> qm=<$qm>\n";
            if ($debug){ echo "#   <$token> case=<$case> qm=<$qm>\n"; }
//            push @path, $token;
            $path[] = $token;


//            NEXT_TOKEN:
            NEXT_TOKEN:
//            if( defined $next_token ) {
            if (isset($next_token)){
//                $debug and print "#   redo <$next_token>\n";
                if ($debug){ echo "#   redo <$next_token>\n"; }
//                $token = $next_token;
                $token = $next_token;
//                $next_token = undef;
                unset($next_token);
//                redo TOKEN;
                goto TOKEN;
//            }
            }
//        }
        }
//    }
    }
//    if( $len < length($record) ) {
    if( $len < strlen($record) ) {
//        # NB: the remainder only arises in the case of degenerate lexer,
//        # and if \Q is operative, the lexer will have been switched to
//        # /\\?./, which means there can never be a remainder, so we
//        # don't have to bother about quotemeta. In other words:
//        # $qm will never be true in this block.
//        my $remain = substr($record,$len); 
        $remain = substr($record,$len); 
//        $case and $remain = $case eq 'U' ? uc($remain) : lc($remain);
        if ($case) {
           $remain = $case == 'U' ? strtoupper($remain) : strtolower($remain);
        }
//        $debug and print "#   add remaining <$remain> case=<$case> qm=<$qm>\n";
        if ($debug){    echo "#   add remaining <$remain> case=<$case> qm=<$qm>\n";    }
//        push @path, $remain;
        $path = perl_push($path, $remain);
//    }
    }
//    $debug and print "# _lex out <@path>\n";
    if ($debug){ echo "# _lex out <@path>\n"; }
    
    
    die;
    
//    return \@path;
    return $path;
//}
}

//sub add {
//    my $self = shift;
function add(){
//    my $record;
    $record = NULL;
//    my $debug  = $self->{debug} & DEBUG_LEX;
    $debug       = $this->debug & $this->DEBUG_LEX;

//    while( defined( $record = shift @_ )) {
    foreach( func_get_args() as $record ) {
//        CORE::chomp($record) if $self->{chomp};
        $record = rtrim($record);
        
//        next if $self->{pre_filter} and not $self->{pre_filter}->($record);
        if ( isset($this->pre_filter) && ! $this->pre_filter($record) ) {
            continue;
        }

//        $debug and print "# add <$record>\n";
        if ($debug){    echo "# add <$record>\n";    }

//        $self->{stats_raw} += length $record;
        $this->stats_raw += strlen($record);

//        my $list = $record =~ /[+*?(\\\[{]/ # }]) restore equilibrium
//            ? $self->{lex} ? $self->_lex($record) : $self->_fastlex($record)
//            : [split //, $record]
//        ;
          $list = 
              preg_match("/[+*?(\\\\\[{]/u" ,$record ) ? //# }]) restore equilibrium
              ($this->lex ? $this->_lex($record) : $this->_fastlex($record) )
              : preg_split("//u" ,$record , -1 , PREG_SPLIT_NO_EMPTY);

//        next if $self->{filter} and not $self->{filter}->(@$list);
          if ( isset($this->filter) && ! $this->filter($list) ) {
              continue;
          }

//        $self->_insertr( $list );
          $this->_insertr( $list );
//    }
    }
//    return $self;
    return $this;
//}
}

/*
=item add_file(FILENAME [...])

Takes a list of file names. Each file is opened and read
line by line. Each line is added to the assembly.

  $r->add_file( 'file.1', 'file.2' );

If a file cannot be opened, the method will croak. If you cannot
afford to let this happen then you should wrap the call in a C<eval>
block.

Chomping happens automatically unless you the C<chomp(0)> method
to disable it. By default, input lines are read according to the
value of the C<input_record_separator> attribute (if defined), and
will otherwise fall back to the current setting of the system C<$/>
variable. The record separator may also be specified on each
call to C<add_file>. Internally, the routine C<local>ises the
value of C<$/> to whatever is required, for the duration of the
call.

An alternate calling mechanism using a hash reference is
available.  The recognised keys are:

=over 4

=item file

Reference to a list of file names, or the name of a single
file.

  $r->add_file({file => ['file.1', 'file.2', 'file.3']});
  $r->add_file({file => 'file.n'});

=item input_record_separator

If present, indicates what constitutes a line

  $r->add_file({file => 'data.txt', input_record_separator => ':' });

=item rs

An alias for input_record_separator (mnemonic: same as the
English variable names).

=back

  $r->add_file( {
    file => [ 'pattern.txt', 'more.txt' ],
    input_record_separator  => "\r\n",
  });

=cut
*/

/* ADODE SKIPなくても動くので省略! 
sub add_file {
    my $self = shift;
    my $rs;
    my @file;
    if (ref($_[0]) eq 'HASH') {
        my $arg = shift;
        $rs = $arg->{rs}
            || $arg->{input_record_separator}
            || $self->{input_record_separator}
            || $/;
        @file = ref($arg->{file}) eq 'ARRAY'
            ? @{$arg->{file}}
            : $arg->{file};
    }
    else {
        $rs   = $self->{input_record_separator} || $/;
        @file = @_;
    }
    local $/ = $rs;
    my $file;
    for $file (@file) {
        open my $fh, '<', $file or do {
            require Carp;
            Carp::croak("cannot open $file for input: $!");
        };
        while (defined (my $rec = <$fh>)) {
            $self->add($rec);
        }
        close $fh;
    }
    return $self;
}
*/

/*
=item insert(LIST)

Takes a list of tokens representing a regular expression and
stores them in the object. Note: you should not pass it a bare
regular expression, such as C<ab+c?d*e>. You must pass it as
a list of tokens, I<e.g.> C<('a', 'b+', 'c?', 'd*', 'e')>.

This method is chainable, I<e.g.>:

  my $ra = Regexp::Assemble->new
    ->insert( qw[ a b+ c? d* e ] )
    ->insert( qw[ a c+ d+ e* f ] );

Lexing complex patterns with metacharacters and so on can consume
a significant proportion of the overall time to build an assembly.
If you have the information available in a tokenised form, calling
C<insert> directly can be a big win.

=cut
*/

//sub insert {
//    my $self = shift;
function insert() {
//    return if $self->{filter} and not $self->{filter}->(@_);
    if ($this->filter) {
        $r = $this->filter( func_get_args() );
        if ($r) return $r;
    }
//    $self->_insertr( [@_] );
    $this->_insertr( func_get_args() );
//    return $self;
    return $this;
//}
}

//sub _insertr {
//    my $self   = shift;
function _insertr() {
//    my $dup    = $self->{stats_dup} || 0;
    $args = func_get_args();
    $dup    = isset($this->stats_dup) ? $this->stats_dup : 0;

//    $self->{path} = $self->_insert_path( $self->_path, $self->_debug(DEBUG_ADD), $_[0] );
    $this->path = $this->_insert_path( $this->path, $this->_debug($this->DEBUG_ADD), $args[0] );

//    if( not defined $self->{stats_dup} or $dup == $self->{stats_dup} ) {
    if ( !isset($this->stats_dup) || $dup == $this->stats_dup ) {
//        ++$self->{stats_add};
        ++$this->stats_add;

//        $self->{stats_cooked} += defined($_) ? length($_) : 0 for @{$_[0]};
        foreach($args[0] as $p){
            $this->stats_cooked += strlen($p);
        }
//    }
    }
//    elsif( $self->{dup_warn} ) {
    else if( $this->dup_warn ) {
//        if( ref $self->{dup_warn} eq 'CODE' ) {
        if ( iscallabe( $this->dup_warn ) ) {
//            $self->{dup_warn}->($self, $_[0]); 
            $this->dup_warn($args[0]); 
//        }
        }
//        else {
        else {
//            my $pattern = join( '', @{$_[0]} );
            $pattern = join( '', $args[0]);
//            require Carp;
//            Carp::carp("duplicate pattern added: /$pattern/");
            trigger_error("duplicate pattern added: /$pattern/");
        }
    }
//    $self->{str} = $self->{re} = undef;
    $this->str = NULL;
    $this->re = NULL;
//}
}

/*
=item lexstr

Use the C<lexstr> method if you are curious to see how a pattern
gets tokenised. It takes a scalar on input, representing a pattern,
and returns a reference to an array, containing the tokenised
pattern. You can recover the original pattern by performing a
C<join>:

  my @token = $re->lexstr($pattern);
  my $new_pattern = join( '', @token );

If the original pattern contains unnecessary backslashes, or C<\x4b>
escapes, or quotemeta escapes (C<\Q>...C<\E>) the resulting pattern
may not be identical.

Call C<lexstr> does not add the pattern to the object, it is merely
for exploratory purposes. It will, however, update various statistical
counters.

=cut
*/

//sub lexstr {
function lexstr($param){
//    return shift->_lex(shift);
    return $this->_lex($param);
//}
}

/*
=item pre_filter(CODE)

Allows you to install a callback to check that the pattern being
loaded contains valid input. It receives the pattern as a whole to
be added, before it been tokenised by the lexer. It may to return
0 or C<undef> to indicate that the pattern should not be added, any
true value indicates that the contents are fine.

A filter to strip out trailing comments (marked by #):

  $re->pre_filter( sub { $_[0] =~ s/\s*#.*$//; 1 } );

A filter to ignore blank lines:

  $re->pre_filter( sub { length(shift) } );

If you want to remove the filter, pass C<undef> as a parameter.

  $ra->pre_filter(undef);

This method is chainable.

=cut
*/

//sub pre_filter {
//    my $self   = shift;
//    my $pre_filter = shift;
function pre_filter($pre_filter = NULL) {
//    if( defined $pre_filter and ref($pre_filter) ne 'CODE' ) {
    if( !iscallabe($pre_filter)) {
//        require Carp;
//        Carp::croak("pre_filter method not passed a coderef");
        trigger_error("pre_filter method not passed a coderef");
//    }
    }
//    $self->{pre_filter} = $pre_filter;
    $this->pre_filter = $pre_filter;
//    return $self;
    return $this;
//}
}

/*
=item filter(CODE)

Allows you to install a callback to check that the pattern being
loaded contains valid input. It receives a list on input, after it
has been tokenised by the lexer. It may to return 0 or undef to
indicate that the pattern should not be added, any true value
indicates that the contents are fine.

If you know that all patterns you expect to assemble contain
a restricted set of of tokens (e.g. no spaces), you could do
the following:

  $ra->filter(sub { not grep { / / } @_ });

or

  sub only_spaces_and_digits {
    not grep { ![\d ] } @_
  }
  $ra->filter( \&only_spaces_and_digits );

These two examples will silently ignore faulty patterns, If you
want the user to be made aware of the problem you should raise an
error (via C<warn> or C<die>), log an error message, whatever is
best. If you want to remove a filter, pass C<undef> as a parameter.

  $ra->filter(undef);

This method is chainable.

=cut
*/
//sub filter {
//    my $self   = shift;
//    my $filter = shift;
function filter($filter = NULL) {
//    if( defined $filter and ref($filter) ne 'CODE' ) {
    if( !iscallabe($filter)) {
//        require Carp;
//        Carp::croak("filter method not passed a coderef");
        trigger_error("filter method not passed a coderef");
//    }
    }
//    $self->{filter} = $filter;
    $this->filter = $filter;
//    return $self;
    return $this;
//}
}
/*
=item as_string

Assemble the expression and return it as a string. You may want to do
this if you are writing the pattern to a file. The following arguments
can be passed to control the aspect of the resulting pattern:

B<indent>, the number of spaces used to indent nested grouping of
a pattern. Use this to produce a pretty-printed pattern (for some
definition of "pretty"). The resulting output is rather verbose. The
reason is to ensure that the metacharacters C<(?:> and C<)> always
occur on otherwise empty lines. This allows you grep the result for an
even more synthetic view of the pattern:

  egrep -v '^ *[()]' <regexp.file>

The result of the above is quite readable. Remember to backslash the
spaces appearing in your own patterns if you wish to use an indented
pattern in an C<m/.../x> construct. Indenting is ignored if tracking
is enabled.

The B<indent> argument takes precedence over the C<indent>
method/attribute of the object.

Calling this
method will drain the internal data structure. Large numbers of patterns
can eat a significant amount of memory, and this lets perl recover the
memory used for other purposes.

If you want to reduce the pattern I<and> continue to add new patterns,
clone the object and reduce the clone, leaving the original object intact.

=cut
*/

//sub as_string {
//    my $self = shift;
function as_string() {
//    if( not defined $self->{str} ) {

    if ( !$this->str ) {
//        if( $self->{track} ) {
        if ( $this->track ) {
//            $self->{m}      = undef;
            $this->m      = NULL;
//            $self->{mcount} = 0;
            $this->mcount = 0;
//            $self->{mlist}  = [];
            $this->mlist  = [];
//            $self->{str}    = _re_path_track($self, $self->_path, '', '');
            $this->str    = $this->_re_path_track($this->path, '', '');
//        }
        }
//        else {
        else {
//            $self->_reduce unless ($self->{mutable} or not $self->{reduce});
            if (! ($this->mutable || !$this->reduce) ) {
                $this->_reduce();
            }
//            my $arg  = {@_};
            $arg  = func_get_args();
//            $arg->indent = $self->indent;
//                if not exists $arg->{indent} and $self->{indent} > 0;
            if ( !isset($arg['indent']) && $this->indent > 0){
                $arg['indent'] = $this->indent;
            }
//            if( exists $arg->{indent} and $arg->{indent} > 0 ) {
            if( isset($arg['indent']) &&  $arg['indent'] && $arg['indent'] > 0 ) {
//                $arg->{depth} = 0;
                $arg['depth'] = 0;
//                $self->{str}  = _re_path_pretty($self, $self->_path, $arg);
                $this->str  = $this->_re_path_pretty($this->path, $arg);
//            }
            }
//            elsif( $self->{lookahead} ) {
            else if( $this->lookahead ) {
//                $self->{str}  = _re_path_lookahead($self, $self->_path);
                $this->str  = $this->_re_path_lookahead($this->path);
//            }
            }
//            else {
            else {
//                $self->{str}  = _re_path($self, $self->_path);
                $this->str  = $this->_re_path($this->path);
//            }
            }
//        }
        }
//        if (not length $self->{str}) {
        if (! strlen($this->str) ) {
//            # explicitly fail to match anything if no pattern was generated
//            $self->{str} = $Always_Fail;
            $this->str = $this->Always_Fail;
//        }
        }
//        else {
        else {
//            my $begin = 
//                  $self->{anchor_word_begin}   ? '\\b'
//                : $self->{anchor_line_begin}   ? '^'
//                : $self->{anchor_string_begin} ? '\A'
//                : ''
//            ;
            $begin = 
                  $this->anchor_word_begin   ? '\\b'
                : $this->anchor_line_begin   ? '^'
                : $this->anchor_string_begin ? '\A'
                : ''
            ;
//            my $end = 
//                  $self->{anchor_word_end}            ? '\\b'
//                : $self->{anchor_line_end}            ? '$'
//                : $self->{anchor_string_end}          ? '\Z'
//                : $self->{anchor_string_end_absolute} ? '\z'
//                : ''
//            ;
            $end = 
                  $this->anchor_word_end            ? '\\b'
                : $this->anchor_line_end            ? '$'
                : $this->anchor_string_end          ? '\Z'
                : $this->anchor_string_end_absolute ? '\z'
                : ''
            ;
//            $self->{str} = "$begin$self->{str}$end";
            $this->str = "{$begin}{$this->str}{$end}";
//        }
        }
//        $self->{path} = [] unless $self->{mutable};
        if (!$this->mutable) {
           $this->path = [];
        }
//    }
    }
//    return $self->{str};
    return $this->str;
//}
}
/*
=item re

Assembles the pattern and return it as a compiled RE, using the
C<qr//> operator.

As with C<as_string>, calling this method will reset the internal data
structures to free the memory used in assembling the RE.

The B<indent> attribute, documented in the C<as_string> method, can be
used here (it will be ignored if tracking is enabled).

With method chaining, it is possible to produce a RE without having
a temporary C<Regexp::Assemble> object lying around, I<e.g.>:

  my $re = Regexp::Assemble->new
    ->add( q[ab+cd+e] )
    ->add( q[ac\\d+e] )
    ->add( q[c\\d+e] )
    ->re;

The C<$re> variable now contains a Regexp object that can be used
directly:

  while( <> ) {
    /$re/ and print "Something in [$_] matched\n";
  )

The C<re> method is called when the object is used in string context
(hence, within an C<m//> operator), so by and large you do not even
need to save the RE in a separate variable. The following will work
as expected:

  my $re = Regexp::Assemble->new->add( qw[ fee fie foe fum ] );
  while( <IN> ) {
    if( /($re)/ ) {
      print "Here be giants: $1\n";
    }
  }

This approach does not work with tracked patterns. The
C<match> and C<matched> methods must be used instead, see below.

=cut
*/
//sub re {
//    my $self = shift;
function re($args = [] ) {
//    $self->_build_re($self->as_string(@_)) unless defined $self->{re};
    if (!$this->re) {
        $this->_build_re($this->as_string($args));
    }
//    return $self->{re};
    return $this->re;
//}
}

//use overload '""' => sub {
//    my $self = shift;
function __toString() {
//    return $self->{re} if $self->{re};
    if ($this->re) {
        return $this->re;
    }
//    $self->_build_re($self->as_string());
    $this->_build_re($this->as_string());
//    return $self->{re};
    return $this->re;
//};
}

//sub _build_re {
//    my $self  = shift;
//    my $str   = shift;
function _build_re($str) {
//    if( $self->{track} ) {
    if ( $this->track ) {
//        use re 'eval';
//        $self->{re} = length $self->{flags}
//            ? qr/(?$self->{flags}:$str)/
//            : qr/$str/
//        ;
        $this->re = strlen($this->flags)
             ? "/(?{$this->flags}:{$str})/"
             : "/{$str}/"
             ;
//    }
    }
//    else {
    else {
//        # how could I not repeat myself?
//        $self->{re} = length $self->{flags}
//            ? qr/(?$self->{flags}:$str)/
//            : qr/$str/
//        ;
        $this->re = strlen($this->flags)
            ? "/(?{$this->flags}:{$str})/"
            : "/{$str}/";
        ;
//    }
    }
//}
}

/*
=item match(SCALAR)

The following information applies to Perl 5.8 and below. See
the section that follows for information on Perl 5.10.

If pattern tracking is in use, you must C<use re 'eval'> in order
to make things work correctly. At a minimum, this will make your
code look like this:

    my $did_match = do { use re 'eval'; $target =~ /$ra/ }
    if( $did_match ) {
        print "matched ", $ra->matched, "\n";
    }

(The main reason is that the C<$^R> variable is currently broken
and an ugly workaround that runs some Perl code during the match
is required, in order to simulate what C<$^R> should be doing. See
Perl bug #32840 for more information if you are curious. The README
also contains more information). This bug has been fixed in 5.10.

The important thing to note is that with C<use re 'eval'>, THERE
ARE SECURITY IMPLICATIONS WHICH YOU IGNORE AT YOUR PERIL. The problem
is this: if you do not have strict control over the patterns being
fed to C<Regexp::Assemble> when tracking is enabled, and someone
slips you a pattern such as C</^(?{system 'rm -rf /'})/> and you
attempt to match a string against the resulting pattern, you will
know Fear and Loathing.

What is more, the C<$^R> workaround means that that tracking does
not work if you perform a bare C</$re/> pattern match as shown
above. You have to instead call the C<match> method, in order to
supply the necessary context to take care of the tracking housekeeping
details.

   if( defined( my $match = $ra->match($_)) ) {
       print "  $_ matched by $match\n";
   }

In the case of a successful match, the original matched pattern
is returned directly. The matched pattern will also be available
through the C<matched> method.

(Except that the above is not true for 5.6.0: the C<match> method
returns true or undef, and the C<matched> method always returns
undef).

If you are capturing parts of the pattern I<e.g.> C<foo(bar)rat>
you will want to get at the captures. See the C<mbegin>, C<mend>,
C<mvar> and C<capture> methods. If you are not using captures
then you may safely ignore this section.

In 5.10, since the bug concerning C<$^R> has been resolved, there
is no need to use C<re 'eval'> and the assembled pattern does
not require any Perl code to be executed during the match.

=cut
*/

//sub match {
//    my $self = shift;
//    my $target = shift;
function match($target) {
    $pregNum = [];
//    $self->_build_re($self->as_string(@_)) unless defined $self->{re};
    if ($this->re) {
        $this->_build_re($this->as_string(func_get_args()));
    }
//    $self->{m}    = undef;
    $this->m = NULL;
//    $self->{mvar} = [];
    $this->mvar = [];

//    if( not $target =~ /$self->{re}/ ) {
    if( !preg_match("/{$this->re}/u",$target,$pregNum, PREG_OFFSET_CAPTURE) ) {
//        $self->{mbegin} = [];
        $this->mbegin = [];
//        $self->{mend}   = [];
        $this->mend = [];
//        return undef;
        return NULL;
//    }
    }
//    $self->{m}      = $^R if $] >= 5.009005;
    $this->m = $pregNum[-1][0];
//    $self->{mbegin} = _path_copy([@-]);
    $this->mbegin = $this->_path_copy($pregNum[1][1]);
//    $self->{mend}   = _path_copy([@+]);
    $this->mend = $this->_path_copy($pregNum[-1][1]);
//    my $n = 0;
    $n = 0;
//kokokara
//http://imawamukashi.web.fc2.com/Perl/TokushuHensu.html#array_last_match_start
//    for( my $n = 0; $n < @-; ++$n ) {
    for($n = 0 ; $n < count($pregNum) ; $n ++ ) {
//        push @{$self->{mvar}}, substr($target, $-[$n], $+[$n] - $-[$n])
//            if defined $-[$n] and defined $+[$n];
          if ($pregNum[$n][1] && $pregNum[-1 * $n][1] ) {
               $this->mvar[] = substr($target, $pregNum[$n][1], $pregNum[-1 * $n][1] - $pregNum[$n][1]);
          }
//    }
    }
//    if( $self->{track} ) {
    if( $this->track ) {
//        return defined $self->{m} ? $self->{mlist}[$self->{m}] : 1;
        return $this->m ? $this->mlist[$this->m] : 1;
//    }
    }
//    else {
    else {
//        return 1;
        return 1;
//    }
    }
//}
}
/*
=item source

When using tracked mode, after a successful match is made, returns
the original source pattern that caused the match. In Perl 5.10,
the C<$^R> variable can be used to as an index to fetch the correct
pattern from the object.

If no successful match has been performed, or the object is not in
tracked mode, this method returns C<undef>.

  my $r = Regexp::Assemble->new->track(1)->add(qw(foo? bar{2} [Rr]at));

  for my $w (qw(this food is rather barren)) {
    if ($w =~ /$r/) {
      print "$w matched by ", $r->source($^R), $/;
    }
    else {
      print "$w no match\n";
    }
  }

=cut
*/
//sub source {
//    my $self = shift;
function source($p1) {
//    return unless $self->{track};
    if (! $this->track ) {
        return $this->track;
    }
//    defined($_[0]) and return $self->{mlist}[$_[0]];
    if ($p1) {
       return $this->mlist[$p1];
    }
//    return unless defined $self->{m};
    if ( ! $this->m ) {
       return $this->m;
    }
//    return $self->{mlist}[$self->{m}];
    return $this->mlist[$this->{m}];
//}
}
/*
=item mbegin

This method returns a copy of C<@-> at the moment of the
last match. You should ordinarily not need to bother with
this, C<mvar> should be able to supply all your needs.

=cut
*/
//sub mbegin {
//    my $self = shift;
function mbegin() {
//    return exists $self->{mbegin} ? $self->{mbegin} : [];
    return is_array($this->mbegin) ? $this->mbegin : [];
//}
}
/*
=item mend

This method returns a copy of C<@+> at the moment of the
last match.

=cut
*/
//sub mend {
//    my $self = shift;
function mend() {
//    return exists $self->{mend} ? $self->{mend} : [];
    return is_array($this->mend) ? $this->mend : [];
//}
}
/*
=item mvar(NUMBER)

The C<mvar> method returns the captures of the last match.
C<mvar(1)> corresponds to $1, C<mvar(2)> to $2, and so on.
C<mvar(0)> happens to return the target string matched,
as a byproduct of walking down the C<@-> and C<@+> arrays
after the match.

If called without a parameter, C<mvar> will return a
reference to an array containing all captures.

=cut
*/
//sub mvar {
//    my $self = shift;
function mvar($p1 = NULL) {
//    return undef unless exists $self->{mvar};
    if ( ! is_array($this->mvar) ) {
        return NULL;
    }
//    return defined($_[0]) ? $self->{mvar}[$_[0]] : $self->{mvar};
    return $_[0] === NULL ? $this->mvar[$p1] : $this->mvar;
//}
}
/*
=item capture

The C<capture> method returns the the captures of the last
match as an array. Unlink C<mvar>, this method does not
include the matched string. It is equivalent to getting an
array back that contains C<$1, $2, $3, ...>.

If no captures were found in the match, an empty array is
returned, rather than C<undef>. You are therefore guaranteed
to be able to use C<< for my $c ($re->capture) { ... >>
without have to check whether anything was captured.

=cut
*/
//sub capture {
//    my $self = shift;
function capture() {
//    if( $self->{mvar} ) {
    if( $this->mvar ) {
//        my @capture = @{$self->{mvar}};
        $capture = $this->mvar;
//        shift @capture;
        array_shift($capture);
//        return @capture;
        return $capture;
//    }
    }
//    return ();
    return [];
//}
}
/*
=item matched

If pattern tracking has been set, via the C<track> attribute,
or through the C<track> method, this method will return the
original pattern of the last successful match. Returns undef
match has yet been performed, or tracking has not been enabled.

See below in the NOTES section for additional subtleties of
which you should be aware of when tracking patterns.

Note that this method is not available in 5.6.0, due to
limitations in the implementation of C<(?{...})> at the time.

=cut
*/
//sub matched {
//    my $self = shift;
function matched() {
//    return defined $self->{m} ? $self->{mlist}[$self->{m}] : undef;
    return $this->m ? $this->mlist[$this->m] : NULL;
//}
}
/*
=back

=head2 Statistics/Reporting routines

=over 8

=item stats_add

Returns the number of patterns added to the assembly (whether
by C<add> or C<insert>). Duplicate patterns are not included
in this total.

=cut
*/
//sub stats_add {
//    my $self = shift;
function stats_add() {
//    return $self->{stats_add} || 0;
    return $this->stats_add || 0;
//}
}
/*
=item stats_dup

Returns the number of duplicate patterns added to the assembly.
If non-zero, this may be a sign that something is wrong with
your data (or at the least, some needless redundancy). This may
occur when you have two patterns (for instance, C<a\-b> and
C<a-b>) which map to the same result.

=cut
*/
//sub stats_dup {
//    my $self = shift;
function stats_dup() {
//    return $self->{stats_dup} || 0;
    return $this->stats_dup || 0;
//}
}
/*
=item stats_raw

Returns the raw number of bytes in the patterns added to the
assembly. This includes both original and duplicate patterns.
For instance, adding the two patterns C<ab> and C<ab> will
count as 4 bytes.

=cut
*/
//sub stats_raw {
//    my $self = shift;
function stats_raw() {
//    return $self->{stats_raw} || 0;
    return $this->stats_raw || 0;
//}
}
/*
=item stats_cooked

Return the true number of bytes added to the assembly. This
will not include duplicate patterns. Furthermore, it may differ
from the raw bytes due to quotemeta treatment. For instance,
C<abc\,def> will count as 7 (not 8) bytes, because C<\,> will
be stored as C<,>. Also, C<\Qa.b\E> is 7 bytes long, however,
after the quotemeta directives are processed, C<a\.b> will be
stored, for a total of 4 bytes.

=cut
*/
//sub stats_cooked {
//    my $self = shift;
function stats_cooked() {
//    return $self->{stats_cooked} || 0;
    return $this->stats_cooked || 0;
//}
}
/*
=item stats_length

Returns the length of the resulting assembled expression.
Until C<as_string> or C<re> have been called, the length
will be 0 (since the assembly will have not yet been
performed). The length includes only the pattern, not the
additional (C<(?-xism...>) fluff added by the compilation.

=cut
*/
//sub stats_length {
//    my $self = shift;
function stats_length() {
//    return (defined $self->{str} and $self->{str} ne $Always_Fail) ? length $self->{str} : 0;
    return ( isset($this->str) && $this->str < $this->Always_Fail) ? strlen($this->str) : 0;
//}
}
/*
=item dup_warn(NUMBER|CODEREF)

Turns warnings about duplicate patterns on or off. By
default, no warnings are emitted. If the method is
called with no parameters, or a true parameter,
the object will carp about patterns it has
already seen. To turn off the warnings, use 0 as a
parameter.

  $r->dup_warn();

The method may also be passed a code block. In this case
the code will be executed and it will receive a reference
to the object in question, and the lexed pattern.

  $r->dup_warn(
    sub {
      my $self = shift;
      print $self->stats_add, " patterns added at line $.\n",
          join( '', @_ ), " added previously\n";
    }
  )

=cut
*/
//sub dup_warn {
//    my $self = shift;
//    $self->{dup_warn} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}
/*
=back

=head2 Anchor routines

Suppose you wish to assemble a series of patterns that all begin
with C<^>  and end with C<$> (anchor pattern to the beginning and
end of line). Rather than add the anchors to each and every pattern
(and possibly forget to do so when a new entry is added), you may
specify the anchors in the object, and they will appear in the
resulting pattern, and you no longer need to (or should) put them
in your source patterns. For example, the two following snippets
will produce identical patterns:

  $r->add(qw(^this ^that ^them))->as_string;

  $r->add(qw(this that them))->anchor_line_begin->as_string;

  # both techniques will produce ^th(?:at|em|is)

All anchors are possible word (C<\b>) boundaries, line
boundaries (C<^> and C<$>) and string boundaries (C<\A>
and C<\Z> (or C<\z> if you absolutely need it)).

The shortcut C<anchor_I<mumble>> implies both
C<anchor_I<mumble>_begin> C<anchor_I<mumble>_end> 
is also available. If different anchors are specified
the most specific anchor wins. For instance, if both
C<anchor_word_begin> and C<anchor_line_begin> are
specified, C<anchor_word_begin> takes precedence.

All the anchor methods are chainable.

=over 8

=item anchor_word_begin

The resulting pattern will be prefixed with a C<\b>
word boundary assertion when the value is true. Set
to 0 to disable.

  $r->add('pre')->anchor_word_begin->as_string;
  # produces '\bpre'

=cut
*/
//sub anchor_word_begin {
//    my $self = shift;
//    $self->{anchor_word_begin} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}

/*
=item anchor_word_end

The resulting pattern will be suffixed with a C<\b>
word boundary assertion when the value is true. Set
to 0 to disable.

  $r->add(qw(ing tion))
    ->anchor_word_end
    ->as_string; # produces '(?:tion|ing)\b'

=cut
*/
//sub anchor_word_end {
//    my $self = shift;
//    $self->{anchor_word_end} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}
/*
=item anchor_word

The resulting pattern will be have C<\b>
word boundary assertions at the beginning and end
of the pattern when the value is true. Set
to 0 to disable.

  $r->add(qw(cat carrot)
    ->anchor_word(1)
    ->as_string; # produces '\bca(?:rro)t\b'

=cut
*/
//sub anchor_word {
//    my $self  = shift;
//    my $state = shift;
function anchor_word($state) {
//    $self->anchor_word_begin($state)->anchor_word_end($state);
    $this->anchor_word_begin($state)->anchor_word_end($state);
//    return $self;
    return $this;
//}
}
/*
=item anchor_line_begin

The resulting pattern will be prefixed with a C<^>
line boundary assertion when the value is true. Set
to 0 to disable.

  $r->anchor_line_begin;
  # or
  $r->anchor_line_begin(1);

=cut
*/
//sub anchor_line_begin {
//    my $self = shift;
//    $self->{anchor_line_begin} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}

/*
=item anchor_line_end

The resulting pattern will be suffixed with a C<$>
line boundary assertion when the value is true. Set
to 0 to disable.

  # turn it off
  $r->anchor_line_end(0);

=cut
*/
//sub anchor_line_end {
//    my $self = shift;
//    $self->{anchor_line_end} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}

/*
=item anchor_line

The resulting pattern will be have the C<^> and C<$>
line boundary assertions at the beginning and end
of the pattern, respectively, when the value is true. Set
to 0 to disable.

  $r->add(qw(cat carrot)
    ->anchor_line
    ->as_string; # produces '^ca(?:rro)t$'

=cut
*/
//sub anchor_line {
//    my $self  = shift;
//    my $state = shift;
function anchor_line($state) {
//    $self->anchor_line_begin($state)->anchor_line_end($state);
    $this->anchor_line_begin($state)->anchor_line_end($state);
//    return $self;
    return $this;
//}
}
/*
=item anchor_string_begin

The resulting pattern will be prefixed with a C<\A>
string boundary assertion when the value is true. Set
to 0 to disable.

  $r->anchor_string_begin(1);

=cut
*/
//sub anchor_string_begin {
//    my $self = shift;
//    $self->{anchor_string_begin} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}

/*
=item anchor_string_end

The resulting pattern will be suffixed with a C<\Z>
string boundary assertion when the value is true. Set
to 0 to disable.

  # disable the string boundary end anchor
  $r->anchor_string_end(0);

=cut
*/
//sub anchor_string_end {
//    my $self = shift;
//    $self->{anchor_string_end} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}


/*
=item anchor_string_end_absolute

The resulting pattern will be suffixed with a C<\z>
string boundary assertion when the value is true. Set
to 0 to disable.

  # disable the string boundary absolute end anchor
  $r->anchor_string_end_absolute(0);

If you don't understand the difference between
C<\Z> and C<\z>, the former will probably do what
you want.

=cut
*/
//sub anchor_string_end_absolute {
//    my $self = shift;
//    $self->{anchor_string_end_absolute} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}

/*
=item anchor_string

The resulting pattern will be have the C<\A> and C<\Z>
string boundary assertions at the beginning and end
of the pattern, respectively, when the value is true. Set
to 0 to disable.

  $r->add(qw(cat carrot)
    ->anchor_string
    ->as_string; # produces '\Aca(?:rro)t\Z'

=cut
*/
//sub anchor_string {
//    my $self  = shift;
//    my $state = defined($_[0]) ? $_[0] : 1;
function anchor_string($state = 1) {
//    $self->anchor_string_begin($state)->anchor_string_end($state);
    $this->anchor_string_begin($state)->anchor_string_end($state);
//    return $self;
    return $this;
//}
}

/*
=item anchor_string_absolute

The resulting pattern will be have the C<\A> and C<\z>
string boundary assertions at the beginning and end
of the pattern, respectively, when the value is true. Set
to 0 to disable.

  $r->add(qw(cat carrot)
    ->anchor_string_absolute
    ->as_string; # produces '\Aca(?:rro)t\z'

=cut
*/
//sub anchor_string_absolute {
//    my $self  = shift;
//    my $state = defined($_[0]) ? $_[0] : 1;
function anchor_string_absolute($state = 1) {
//    $self->anchor_string_begin($state)->anchor_string_end_absolute($state);
    $self->anchor_string_begin($state)->anchor_string_end_absolute($state);
    $this->anchor_string_begin($state)->anchor_string_end_absolute($state);
//    return $self;
    return $this;
//}
}
/*
=back

=over 8

=item debug(NUMBER)

Turns debugging on or off. Statements are printed
to the currently selected file handle (STDOUT by default).
If you are already using this handle, you will have to
arrange to select an output handle to a file of your own
choosing, before call the C<add>, C<as_string> or C<re>)
functions, otherwise it will scribble all over your
carefully formatted output.

=over 8

=item 0

Off. Turns off all debugging output.

=item 1

Add. Trace the addition of patterns.

=item 2

Reduce. Trace the process of reduction and assembly.

=item 4

Lex. Trace the lexing of the input patterns into its constituent
tokens.

=item 8

Time. Print to STDOUT the time taken to load all the patterns. This is
nothing more than the difference between the time the object was
instantiated and the time reduction was initiated.

  # load=<num>

Any lengthy computation performed in the client code will be reflected
in this value. Another line will be printed after reduction is
complete.

  # reduce=<num>

The above output lines will be changed to C<load-epoch> and
C<reduce-epoch> if the internal state of the object is corrupted
and the initial timestamp is lost.

The code attempts to load L<Time::HiRes> in order to report fractional
seconds. If this is not successful, the elapsed time is displayed
in whole seconds.

=back

Values can be added (or or'ed together) to trace everything

  $r->debug(7)->add( '\\d+abc' );

Calling C<debug> with no arguments turns debugging off.

=cut
*/
//sub debug {
//    my $self = shift;
//    $self->{debug} = defined($_[0]) ? $_[0] : 0;
function debug($p1 = 0) {
   $this->debug = $p1;
//    if ($self->_debug(DEBUG_TIME)) {
//      if ($this->_debug(DEBUG_TIME)) {
      if (0) { //とりあえず
//        # hmm, debugging time was switched on after instantiation
//        $self->_init_time_func;
        $this->_init_time_func;
//        $self->{_begin_time} = $self->{_time_func}->();
        $this->_begin_time = $this->_time_func();
//    }
    }
//    return $self;
    return $this;
//}
}

/*
=item dump

Produces a synthetic view of the internal data structure. How
to interpret the results is left as an exercise to the reader.

  print $r->dump;

=cut
*/
//sub dump {
function dump($p1) {
//    return _dump($_[0]->_path);
    return $this->_dump($p1->path);
//}
}

/*
=item chomp(0|1)

Turns chomping on or off. 

IMPORTANT: As of version 0.24, chomping is now on by default as it
makes C<add_file> Just Work. The only time you may run into trouble
is with C<add("\\$/")>. So don't do that, or else explicitly turn
off chomping.

To avoid incorporating (spurious)
record separators (such as "\n" on Unix) when reading from a file, 
C<add()> C<chomp>s its input. If you don't want this to happen,
call C<chomp> with a false value.

  $re->chomp(0); # really want the record separators
  $re->add(<DATA>);

=cut
*/
//sub chomp {
//    my $self = shift;
function chomp($p1 = 1) {
//    $self->{chomp} = defined($_[0]) ? $_[0] : 1;
    $this->chomp = $p1;
//    return $self;
    return $this;
//}
}
/*
=item fold_meta_pairs(NUMBER)

Determines whether C<\s>, C<\S> and C<\w>, C<\W> and C<\d>, C<\D>
are folded into a C<.> (dot). Folding happens by default (for
reasons of backwards compatibility, even though it is wrong when
the C</s> expression modifier is active).

Call this method with a false value to prevent this behaviour (which
is only a problem when dealing with C<\n> if the C</s> expression
modifier is also set).

  $re->add( '\\w', '\\W' );
  my $clone = $re->clone;

  $clone->fold_meta_pairs(0);
  print $clone->as_string; # prints '.'
  print $re->as_string;    # print '[\W\w]'

=cut
*/
//sub fold_meta_pairs {
//    my $self = shift;
//    $self->{fold_meta_pairs} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}

/*
=item indent(NUMBER)

Sets the level of indent for pretty-printing nested groups
within a pattern. See the C<as_string> method for more details.
When called without a parameter, no indenting is performed.

  $re->indent( 4 );
  print $re->as_string;

=cut
*/
//sub indent {
//    my $self = shift;
//    $self->{indent} = defined($_[0]) ? $_[0] : 0;
//    return $self;
//}

/*
=item lookahead(0|1)

Turns on zero-width lookahead assertions. This is usually
beneficial when you expect that the pattern will usually fail.
If you expect that the pattern will usually match you will
probably be worse off.

=cut
*/
//sub lookahead {
//    my $self = shift;
//    $self->{lookahead} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}

/*
=item flags(STRING)

Sets the flags that govern how the pattern behaves (for
versions of Perl up to 5.9 or so, these are C<imsx>). By
default no flags are enabled.


=item modifiers(STRING)

An alias of the C<flags> method, for users familiar with
C<Regexp::List>.

=cut
*/
//sub flags {
//    my $self = shift;
function flags($p1 = '') {
//    $self->{flags} = defined($_[0]) ? $_[0] : '';
    $this->flags = $p1;
//    return $self;
    return $this;
//}
}

//sub modifiers {
//    my $self = shift;
function modifiers() {
//    return $self->flags(@_);
    return $this->flags( func_get_args() );
//}
}
/*
=item track(0|1)

Turns tracking on or off. When this attribute is enabled,
additional housekeeping information is inserted into the
assembled expression using C<({...}> embedded code
constructs. This provides the necessary information to
determine which, of the original patterns added, was the
one that caused the match.

  $re->track( 1 );
  if( $target =~ /$re/ ) {
    print "$target matched by ", $re->matched, "\n";
  }

Note that when this functionality is enabled, no
reduction is performed and no character classes are
generated. In other words, C<brag|tag> is not
reduced down to C<(?:br|t)ag> and C<dig|dim> is not
reduced to C<di[gm]>.

=cut
*/
//sub track {
//    my $self = shift;
function track($p1 = 1) {
//    $self->{track} = defined($_[0]) ? $_[0] : 1;
    $this->track = $p1;
//    return $self;
    return $this;
//}
}
/*
=item unroll_plus(0|1)

Turns the unrolling of plus metacharacters on or off. When
a pattern is broken up, C<a+> becomes C<a>, C<a*> (and
C<b+?> becomes C<b>, C<b*?>. This may allow the freed C<a>
to assemble with other patterns. Not enabled by default.

=cut
*/
//sub unroll_plus {
//    my $self = shift;
//    $self->{unroll_plus} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}

/*
=item lex(SCALAR)

Change the pattern used to break a string apart into tokens.
You can examine the C<eg/naive> script as a starting point.

=cut
*/
//sub lex {
//    my $self = shift;
//    $self->{lex} = qr($_[0]);
//    return $self;
//}
/*
=item reduce(0|1)

Turns pattern reduction on or off. A reduced pattern may
be considerably shorter than an unreduced pattern. Consider
C</sl(?:ip|op|ap)/> I<versus> C</sl[aio]p/>. An unreduced
pattern will be very similar to those produced by
C<Regexp::Optimizer>. Reduction is on by default. Turning
it off speeds assembly (but assembly is pretty fast -- it's
the breaking up of the initial patterns in the lexing stage
that can consume a non-negligible amount of time).

=cut
*/
//sub reduce {
//    my $self = shift;
//    $self->{reduce} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}

/*
=item mutable(0|1)

This method has been marked as DEPRECATED. It will be removed
in a future release. See the C<clone> method for a technique
to replace its functionality.

=cut
*/
//sub mutable {
//    my $self = shift;
//    $self->{mutable} = defined($_[0]) ? $_[0] : 1;
//    return $self;
//}

/*
=item reset

Empties out the patterns that have been C<add>ed or C<insert>-ed
into the object. Does not modify the state of controller attributes
such as C<debug>, C<lex>, C<reduce> and the like.

=cut
*/
//sub reset {
function reset() {
//    # reinitialise the internal state of the object
//    my $self = shift;
//    $self->{path} = [];
    $this->path = [];
//    $self->{re}   = undef;
    $this->re   = NULL;
//    $self->{str}  = undef;
    $this->str   = NULL;
//    return $self;
    return $this;
//}
}
/*
=item Default_Lexer

B<Warning:> the C<Default_Lexer> function is a class method, not
an object method. It is a fatal error to call it as an object
method.

The C<Default_Lexer> method lets you replace the default pattern
used for all subsequently created C<Regexp::Assemble> objects. It
will not have any effect on existing objects. (It is also possible
to override the lexer pattern used on a per-object basis).

The parameter should be an ordinary scalar, not a compiled
pattern. If the pattern fails to match all parts of the string,
the missing parts will be returned as single chunks. Therefore
the following pattern is legal (albeit rather cork-brained):

    Regexp::Assemble::Default_Lexer( '\\d' );

The above pattern will split up input strings digit by digit, and
all non-digit characters as single chunks.

=cut
*/
//sub Default_Lexer {
function Default_Lexer($p1) {
//    if( $_[0] ) {
    if ($p1) {
//        if( my $refname = ref($_[0]) ) {
        $refname = gettype($p1);
        if ($refname) {
//            require Carp;
//            Carp::croak("Cannot pass a $refname to Default_Lexer");
            trigger_error("Cannot pass a $refname to Default_Lexer");
//        }
        }
//        $Current_Lexer = $_[0];
        $Current_Lexer = $p1;
//    }
    }
//    return defined $Current_Lexer ? $Current_Lexer : $Default_Lexer;
    return $this->Current_Lexer ? $this->Current_Lexer : $this->Default_Lexer;
//}
}

//# --- no user serviceable parts below ---
//
//# -- debug helpers

//sub _debug {
//    my $self = shift;
function _debug($flg) {
//    return $self->{debug} & shift() ? 1 : 0;
    return $this->debug & $flg ? 1 : 0;
//}
}

# -- helpers

# -- the heart of the matter

////// PHPの場合 clone を使うので事実上呼ばれない
// PHPなら clone でやるべし
//
//$have_Storable = do {
//    eval {
//        require Storable;
//        import Storable 'dclone';
//    };
//    $@ ? 0 : 1;
//};
//
//sub _path_clone {
//    $have_Storable ? dclone($_[0]) : _path_copy($_[0]);
//}

//sub _path_copy {
//    my $path = shift;
function _path_copy($path) {
//    my $new  = [];
    $new = [];
//    for( my $p = 0; $p < @$path; ++$p ) {
    for( $p = 0; $p < count($path) ; ++$p ) {
//        if( ref($path->[$p]) eq 'HASH' ) {
        if( is_array($path[$p]) ){
//            push @$new, _node_copy($path->[$p]);
           $new = perl_push($new , $this->_node_copy($path[$p]) );
//        }
        }
//        elsif( ref($path->[$p]) eq 'ARRAY' ) {
//            push @$new, _path_copy($path->[$p]);
//        }
//arrayで吸収する.
//        else {
        else {
//            push @$new, $path->[$p];
            $new = perl_push( $new, $path[$p] );
//        }
        }
//    }
    }
//    return $new;
    return $new;
//}
}

//sub _node_copy {
//    my $node = shift;
function _node_copy($node) {
//    my $new  = {};
    $new  = [];
//    while( my( $k, $v ) = each %$node ) {
    foreach( $node as $k => $v  ) {
//        $new->{$k} = defined($v)
//            ? _path_copy($v)
//            : undef
//        ;
        $new[$k] = $v != ''
            ? $this->_path_copy($v)
            : NULL
        ;
//    }
    }
//    return $new;
    return $new;
//}
}

//sub _insert_path {
//    my $self  = shift;
//    my $list  = shift;
//    my $debug = shift;
//    my @in    = @{shift()}; # create a new copy
function _insert_path($list , $debug , $in) {
//    if( @$list == 0 ) { # special case the first time
    if (count($list) == 0 ){ 
//        if( @in == 0 or (@in == 1 and (not defined $in[0] or $in[0] eq ''))) {
        if (count($in) == 0 || (count($in) == 1 && (! isset($in[0]) || $in[0] == '' ) ) ) {
//            return [{'' => undef}];
            return ['__@UNDEF@__' => NULL];
//        }
        }
//        else {
        else {
//            return \@in;
            return $in;  /////要注意
//        }
        }
//    }
    }
//    $debug and print "# _insert_path @{[_dump(\@in)]} into @{[_dump($list)]}\n";
    if ($debug) { echo "# _insert_path ".$this->_dump($in)." into ".$this->_dump($list)."\n"; }
//    my $path   = $list;
    $path = $list;
//    my $offset = 0;
    $offset = 0;
//    my $token;
    $token = NULL;
//    if( not @in ) {
    if ( count($in) == 0 ) {
//        if( ref($list->[0]) ne 'HASH' ) {
        if ( !is_array($list[0]) ) {
//            return [ { '' => undef, $list->[0] => $list } ];
            return [ '__@UNDEF@__'=> NULL , $list[0] => $list ];
//        }
        }
//        else {
        else {
//            $list->[0]{''} = undef;
            $list[0]['__@UNDEF@__'] = NULL;
//            return $list;
            return $list;
//        }
        }
//    }
    }
//    while( defined( $token = shift @in )) {
    while( $token = array_shift($in) ) {

//        if( ref($token) eq 'HASH' ) {
        if ( is_array($token) ) {
//            $debug and print "#  p0=", _dump($path), "\n";
            if ($debug) { echo "#  p0=", $this->_dump($path), "\n"; }
//            $path = $self->_insert_node( $path, $offset, $token, $debug, @in );
            $path = $this->_insert_node( $path, $offset, $token, $debug, $in );
//            $debug and print "#  p1=", _dump($path), "\n";
            if ($debug){ echo "#  p1=", $this->_dump($path), "\n"; }
//            last;
            break;
//        }
        }

//        if( ref($path->[$offset]) eq 'HASH' ) {
        if ( is_array($path[$offset]) ) {
//            $debug and print "#   at (off=$offset len=@{[scalar @$path]}) ", _dump($path->[$offset]), "\n";
            if ($debug) { echo "#   at (off=$offset len=".count($path).",". $this->_dump([$path[$offset]]). "\n"; }
//            my $node = $path->[$offset];
            $node = $path[$offset];
//            if( exists( $node->{$token} )) {
            if( isset( $node[$token] ) ) {
//                if ($offset < $#$path) {
                if ($offset < perl_lastindex($path) ) { 
//                    my $new = {
//                        $token => [$token, @in],
//                        _re_path($self, [$node]) => [@{$path}[$offset..$#$path]],
//                    };
                    $new = [
                         $token => perl_array($token, $in) ,
                         $this->_re_path($node) => array_slice($path,$offset)
                    ];
//                    splice @$path, $offset, @$path-$offset, $new;
                    array_splice($path, $offset, count($path)-$offset, $new);
//                    last;
                    break;
//                }
                }
//                else {
                else {
//                    $debug and print "#   descend key=$token @{[_dump($node->{$token})]}\n";
                    if ( $debug ) { echo "#   descend key=$token .".$this->_dump($node[$token])."\n"; }
//                    $path   = $node->{$token};
//                    $offset = 0;
//                    redo;
                    //オリジナルは参照を使って実装しているが、再起を使って実装してみる。
                    $path[$offset][$token] = $this->_insert_path( $path[$offset][$token] , $debug , perl_array($token, $in) ) ;
                    return $path;
//                }
                }
//            }
            }
//            else {
            else {
//                $debug and print "#   add path ($token:@{[_dump(\@in)]}) into @{[_dump($path)]} at off=$offset to end=@{[scalar $#$path]}\n";
                if ($debug) { echo "#   add path ($token:".$this->_dump($in).") into ".$this->_dump($path)." at off=$offset to end=".perl_lastindex($path)."\n"; }
//                if( $offset == $#$path ) {
                if ( $offset == perl_lastindex($path)  ) {
//                    $node->{$token} = [ $token, @in ];
                      $path[$offset][$token] = perl_array($token,$in); //nodeを参照にしていないため $path で受ける.
                    if ($debug) { echo "#   offset({$offset}) eq lastindex=".perl_lastindex($path)." path=".$this->_dump($path)."\n"; }
//                }
                }
//                else {
                else {
//                    my $new = {
//                        _node_key($token) => [ $token, @in ],
//                        _node_key($node)  => [@{$path}[$offset..$#{$path}]],
//                    };
                    $new = [
                         $this->_node_key( $token ) => [$token, $in],
                         $this->_node_key( $node ) => array_slice($path,$offset)
                    ];
//                    splice( @$path, $offset, @$path - $offset, $new );
                    array_splice( $path, $offset, count($path) - $offset, $new );
//                    $debug and print "#   fused node=@{[_dump($new)]} path=@{[_dump($path)]}\n";
                    if ($debug) { echo "#   fused node=".$this->_dump($new)." path=".$this->_dump($path)."\n"; }
//                }
                }
//                last;
                break;
//            }
            }
//        }
        }

//        if( $debug ) {
        if ($debug) {
//            my $msg = '';
            $msg = '';
//            my $n;
//            for( $n = 0; $n < @$path; ++$n ) {
            for( $n = 0; $n < count($path) ; ++$n ) {
//                $msg .= ' ' if $n;
                if ($n) $msg .= ' ';
//                my $atom = ref($path->[$n]) eq 'HASH'
//                    ? '{'.join( ' ', keys(%{$path->[$n]})).'}'
//                    : $path->[$n]
//                ;
                $atom = is_array($path[$n])
                    ? '{'.join( ' ', array_keys($path[$n])).'}'
                    : $path[$n]
                ;
//                $msg .= $n == $offset ? "<$atom>" : $atom;
                $msg .= ($n == $offset ? "<$atom>" : $atom);
//            }
            }
//            print "# at path ($msg)\n";
            echo "# at path ($msg)\n";
//        }
        }

//        if( $offset >= @$path ) {
        if( $offset >= count($path) ) {
//            push @$path, { $token => [ $token, @in ], '' => undef };
            $path = perl_push($path,  [ $token => [ $token, $in ], '__@UNDEF@__' => NULL ] );
//            $debug and print "#   added remaining @{[_dump($path)]}\n";
            if ($debug) { echo "#   added remaining ".$this->_dump($path)."\n"; }
//            last;
            break;
//        }
        }
//        elsif( $token ne $path->[$offset] ) {
        else if ( $token != $path[$offset] ) {
//            $debug and print "#   token $token not present\n";
            if ($debug) { echo "#   token $token not present\n"; }
//            splice @$path, $offset, @$path-$offset, {
//                length $token
//                    ? ( _node_key($token) => [$token, @in])
//                    : ( '' => undef )
//                ,
//                $path->[$offset] => [@{$path}[$offset..$#{$path}]],
//            };
            $_temp_path = strlen($token) 
                ?  [ $this->_node_key($token) => perl_array($token , $in) ]
                :  [ '__@UNDEF@__' => NULL ]
            ;
            $_temp_path[ $path[$offset] ] = array_slice($path , $offset);
            array_splice($path, $offset, count($path)-$offset, [ $_temp_path ] );

//            $debug and print "#   path=@{[_dump($path)]}\n";
            if ( $debug ){ echo  "#   path=".$this->_dump($path)."\n"; }
//            last;
            break;
//        }
        }
//        elsif( not @in ) {
        else if( ! count($in) ) {
//            $debug and print "#   last token to add\n";
            if ( $debug ){ echo "#   last token to add\n"; };
//            if( defined( $path->[$offset+1] )) {
            if( isset( $path[$offset+1] )) {
//                ++$offset;
                ++$offset;
//                if( ref($path->[$offset]) eq 'HASH' ) {
                if( is_array($path[$offset]) ) {
//                    $debug and print "#   add sentinel to node\n";
                    if ($debug) { echo "#   add sentinel to node\n"; }
//                    $path->[$offset]{''} = undef;
                    $path[$offset]['__@UNDEF@__'] = NULL;
//                }
                }
//                else {
                else {
//                    $debug and print "#   convert <$path->[$offset]> to node for sentinel\n";
                    if ($debug) { echo "#   convert <$path->[$offset]> to node for sentinel\n"; }
//                    splice @$path, $offset, @$path-$offset, {
//                        ''               => undef,
//                        $path->[$offset] => [ @{$path}[$offset..$#{$path}] ],
//                    };
                      array_splice($path, $offset, count($path)-$offset,
                              array('__@UNDEF@__' => NULL ,
                                    $path[$offset] => array_slice($path,$offset)
                              )
                      );
//                }
                }
//            }
            }
//            else {
            else {
//                # already seen this pattern
//                ++$self->{stats_dup};
                ++$this->stats_dup;
//            }
            }
//            last;
            break;
//        }
        }
//        # if we get here then @_ still contains a token
//        ++$offset;
        ++$offset;
//    }
    }
//    $list;
    return $path;
//}
}

//sub _insert_node {
//    my $self   = shift;
//    my $path   = shift;
//    my $offset = shift;
//    my $token  = shift;
//    my $debug  = shift;
function _insert_node($path,$offset,$token,$debug) {
    $_args = func_get_args();

//    my $path_end = [@{$path}[$offset..$#{$path}]];
    $path_end = array_slice($path , $offset);
//    # NB: $path->[$offset] and $[path_end->[0] are equivalent
//    my $token_key = _re_path($self, [$token]);
    $token_key = $this->_re_path($token);
//    $debug and print "#  insert node(@{[_dump($token)]}:@{[_dump(\@_)]}) (key=$token_key)",
//        " at path=@{[_dump($path_end)]}\n";
    if ($debug) { echo "#  insert node(".$this->_dump($token).":".$this->_dump($_args).") (key=$token_key)" . " at path=".$this->_dump($path_end)."\n"; }
//    if( ref($path_end->[0]) eq 'HASH' ) {
    if ( is_array($path_end[0]) ) {
//        if( exists($path_end->[0]{$token_key}) ) {
        if( isset($path_end[0][$token_key]) ) {
//            if( @$path_end > 1 ) {
            if( count($path_end) > 1 ) {
//                my $path_key = _re_path($self, [$path_end->[0]]);
                $path_key = $this->_re_path($path_end[0]);
//                my $new = {
//                    $path_key  => [ @$path_end ],
//                    $token_key => [ $token, @_ ],
//                };
                $new = [
                    $path_key  => [ $path_end ],
                    $token_key => [ $token, $_args ]
                ];
//                $debug and print "#   +bifurcate new=@{[_dump($new)]}\n";
                if ($debug){ echo "#   +bifurcate new=".$this->_dump($new)."\n"; }
//                splice( @$path, $offset, @$path_end, $new );
                array_splice( $path, $offset, count($path_end), $new );
//            }
            }
//            else {
            else {
//                my $old_path = $path_end->[0]{$token_key};
                $old_path = $path_end[0][$token_key];
//                my $new_path = [];
                $new_path = [];
//                while( @$old_path and _node_eq( $old_path->[0], $token )) {
                while( $old_path and $this->_node_eq( $old_path[0], $token )) {
//                    $debug and print "#  identical nodes in sub_path ",
//                        ref($token) ? _dump($token) : $token, "\n";
                    if ( $debug ){ echo "#  identical nodes in sub_path ". (is_array($token) ? $this->_dump($token) : $token) . "\n"; }
//                    push @$new_path, shift(@$old_path);
                    $new_path = perl_push($new_path, array_shift($old_path) );
//                    $token = shift @_;
                    $token = array_shift($_args);
//                }
                }
//                if( @$new_path ) {
                if( is_array($new_path) && count($new_path) ) {
//                    my $new;
                    $new = NULL;
//                    my $token_key = $token;
                    $token_key = $token;
//                    if( @_ ) {
                    if( $_args ) {
//                        $new = {
//                            _re_path($self, $old_path) => $old_path,
//                            $token_key => [$token, @_],
//                        };
                        $new = [
                            $this->_re_path($old_path) => $old_path,
                            $token_key => [$token, $_args ]
                        ];
//                        $debug and print "#  insert_node(bifurc) n=@{[_dump([$new])]}\n";
                        if ($debug) { echo "#  insert_node(bifurc) n=".$this->_dump($new)."\n"; }
//                    }
                    }
//                    else {
                    else {
//                        $debug and print "#  insert $token into old path @{[_dump($old_path)]}\n";
                        if ( $debug ) { echo "#  insert $token into old path ".$this->_dump($old_path)."\n"; };
//                        if( @$old_path ) {
                        if( is_array($old_path) && count($old_path) ) {
//                            $new = ($self->_insert_path( $old_path, $debug, [$token] ))->[0];
                            $new = $this->_insert_path( $old_path, $debug, [$token] );
                            $new = $new[0];
//                        }
                        }
//                        else {
                        else {
//                            $new = { '' => undef, $token => [$token] };
                            $new = [ '__@UNDEF@__' => NULL , $token => [$token] ];
//                        }
                        }
//                    }
                    }
//                    push @$new_path, $new;
                    $new_path = perl_push( $new_path , $new );
//                }
                }
//                $path_end->[0]{$token_key} = $new_path;
                $path_end[0][$token_key] = $new_path;
//                $debug and print "#   +_insert_node result=@{[_dump($path_end)]}\n";
                if ($debug) { echo "#   +_insert_node result=".$this->_dump($path_end)."\n"; }
//                splice( @$path, $offset, @$path_end, @$path_end );
                array_splice( $path, $offset, count($path_end), $path_end );
//            }
            }
//        }
        }
//        elsif( not _node_eq( $path_end->[0], $token )) {
        else if( ! $this->_node_eq( $path_end[0], $token )) {
//            if( @$path_end > 1 ) {
            if( count($path_end) > 1 ) {
//                my $path_key = _re_path($self, [$path_end->[0]]);
                $path_key = $this->_re_path($path_end[0]);
//                my $new = {
//                    $path_key  => [ @$path_end ],
//                    $token_key => [ $token, @_ ],
//                };
//                $debug and print "#   path->node1 at $path_key/$token_key @{[_dump($new)]}\n";
                if ( $debug ){ echo "#   path->node1 at $path_key/$token_key ".$this->_dump($new)."\n"; }
//                splice( @$path, $offset, @$path_end, $new );
                array_splice( $path, $offset, count($path_end), $new );
//            }
            }
//            else {
            else {
//                $debug and print "#   next in path is node, trivial insert at $token_key\n";
                if ($debug){ echo "#   next in path is node, trivial insert at $token_key\n"; }
//                $path_end->[0]{$token_key} = [$token, @_];
                $path_end[0][$token_key] = [$token, $_args];
//                splice( @$path, $offset, @$path_end, @$path_end );
                array_splice( $path, $offset, count($path_end), $path_end );
//            }
            }
//        }
        }
//        else {
        else {
//            while( @$path_end and _node_eq( $path_end->[0], $token )) {
            while( is_array($path_end) && $this->_node_eq( $path_end[0], $token )) {
//                $debug and print "#  identical nodes @{[_dump([$token])]}\n";
                if ($debug) { echo "#  identical nodes ".$this->_dump($token)."\n"; }
//                shift @$path_end;
                array_shift( $path_end );
//                $token = shift @_;
                $token = array_shift($_args);
//                ++$offset;
                ++$offset;
//            }
            }
//            if( @$path_end ) {
            if( is_array($path_end) ) {
//                $debug and print "#   insert at $offset $token:@{[_dump(\@_)]} into @{[_dump($path_end)]}\n";
                if ($debug) { echo "#   insert at $offset $token:".$this->_dump($_args)." into ".$this->_dump($path_end)."\n"; }
//                $path_end = $self->_insert_path( $path_end, $debug, [$token, @_] );
                $path_end = $this->_insert_path( $path_end, $debug, [$token, $_args] );
//                $debug and print "#   got off=$offset s=@{[scalar @_]} path_add=@{[_dump($path_end)]}\n";
                if ($debug) { echo "#   got off=$offset s=".count($_args)." path_add=".$this->_dump($path_end)."\n"; }
//                splice( @$path, $offset, @$path - $offset, @$path_end );
                array_splice( $path, $offset, count($path) - $offset, $path_end );
//                $debug and print "#   got final=@{[_dump($path)]}\n";
                if ( $debug ){ echo "#   got final=".$this->_dump($path)."\n"; }
//            }
            }
//            else {
            else {
//                $token_key = _node_key($token);
                $token_key = $this->_node_key($token);
//                my $new = {
//                    ''         => undef,
//                    $token_key => [ $token, @_ ],
//                };
                $new = [
                    '__@UNDEF@__'         => NULL,
                    $token_key => [ $token, $_args ],
                ];
//                $debug and print "#   convert opt @{[_dump($new)]}\n";
                if ( $debug ) { echo "#   convert opt ".$this->_dump($new)."\n"; }
//                push @$path, $new;
                $path = perl_push($path , $new );
//            }
            }
//        }
        }
//    }
    }
//    else {
    else {
//        if( @$path_end ) {
        if( is_array($path_end) ) {
//            my $new = {
//                $path_end->[0] => [ @$path_end ],
//                $token_key     => [ $token, @_ ],
//            };
            $new = [
                $path_end[0] => [ @$path_end ],
                $token_key   => [ $token, $_args ]
            ];
//            $debug and print "#   atom->node @{[_dump($new)]}\n";
            if ( $debug ){ echo "#   atom->node ".$this->_dump($new)."\n"; }
//            splice( @$path, $offset, @$path_end, $new );
            array_splice( $path, $offset, count($path_end), $new );
//            $debug and print "#   out=@{[_dump($path)]}\n";
            if ( $debug ) { echo "#   out=".$this->_dump($path)."\n"; }
//        }
        }
//        else {
        else {
//            $debug and print "#   add opt @{[_dump([$token,@_])]} via $token_key\n";
            if ( $debug ) { echo "#   add opt ".$this->_dump([$token,$_args])." via $token_key\n"; }
//            push @$path, {
//                ''         => undef,
//                $token_key => [ $token, @_ ],
//            };
            $path = perl_push( $path , [
                  '__@UNDEF@__' => NULL,
                  $token_key => [ $token, $_args ]
                ]
            );
//        }
        }
//    }
    }
//    $path;
    return $path;
//}
}

//sub _reduce {
//    my $self    = shift;
function _reduce() {
//    my $context = { debug => $self->_debug(DEBUG_TAIL), depth => 0 };
    $context = [ 'debug' => $this->_debug($this->DEBUG_TAIL), 'depth' => 0 ];
/*
//skip debug
//    if ($self->_debug(DEBUG_TIME)) {
     if ($this->_debug($this->DEBUG_TIME)) {
//        $self->_init_time_func;
        $this->_init_time_func();
//        my $now = $self->{_time_func}->();
        $now = $this->_time_func();
//        if (exists $self->{_begin_time}) {
        if ( isset($this->_begin_time) ) {
//            printf "# load=%0.6f\n", $now - $self->{_begin_time};
            echo "# load=%0.6f\n", $now - $this->_begin_time;
//        }
        }
//        else {
        else {
//            printf "# load-epoch=%0.6f\n", $now;
            echo "# load-epoch=%0.6f\n", $now;
//        }
        }
//        $self->{_begin_time} = $self->{_time_func}->();
        $this->_begin_time = $this->_time_func();
//    }
    }
*/

//    my ($head, $tail) = _reduce_path( $self->_path, $context );
    list($head, $tail) = $this->_reduce_path( $this->path, $context );
//    $context->{debug} and print "# final head=", _dump($head), ' tail=', _dump($tail), "\n";
    if ( $context['debug'] ) { echo "# final head=", $this->_dump($head), ' tail=', $this->_dump($tail), "\n"; }
//    if( !@$head ) {
    if( ! count($head) ) {
//        $self->{path} = $tail;
        $this->path = $tail;
//    }
    }
//    else {
    else {
//        $self->{path} = [
//            @{_unrev_path( $tail, $context )},
//            @{_unrev_path( $head, $context )},
//        ];
        $this->path = perl_array(
            $this->_unrev_path( $tail, $context ),
            $this->_unrev_path( $head, $context )
        );
//    }
    }

/*
skip debug
//    if ($self->_debug(DEBUG_TIME)) {
    if ($this->_debug(DEBUG_TIME)) {
//        my $now = $self->{_time_func}->();
        $now = $this->_time_func();
//        if (exists $self->{_begin_time}) {
        if ( isset( $this->_begin_time ) ) {
//            printf "# reduce=%0.6f\n", $now - $self->{_begin_time};
            echo "# reduce=%0.6f\n", $now - $self->{_begin_time};
//        }
        }
//        else {
        else {
//            printf "# reduce-epoch=%0.6f\n", $now;
            echo "# reduce-epoch=%0.6f\n", $now;
//        }
        }
//        $self->{_begin_time} = $self->{_time_func}->();
        $this->_begin_time = $this->_time_func();
//    }
    }
*/

//    $context->{debug} and print "# final path=", _dump($self->{path}), "\n";
    if ( $context['debug'] ) { echo "# final path=", $this->_dump($this->path), "\n"; }
//    return $self;
    return $this;
//}
}

//sub _remove_optional {
function _remove_optional(&$p1) {

//    if( exists $_[0]->{''} ) {
    if( isset($p1['__@UNDEF@__']) ) {
//        delete $_[0]->{''};
        unset( $p1['__@UNDEF@__'] );
//        return 1;
        return 1;
//    }
    }
//    return 0;
    return 0;
//}
}

//kokokara
//sub _reduce_path {
//    my ($path, $ctx) = @_;
function _reduce_path($path, $ctx) {
//    my $indent = ' ' x $ctx->{depth};
    $indent = str_repeat (' ' , $ctx['depth']);
//    my $debug  =       $ctx->{debug};
    $debug  =       $ctx['debug'];

//    $debug and print "#$indent _reduce_path $ctx->{depth} ", _dump($path), "\n";
    if ($debug) { echo "#$indent _reduce_path {$ctx['depth']} ", $this->_dump($path), "\n"; }
//    my $new;
    $new = NULL;
//    my $head = [];
    $head = [];
//    my $tail = [];
    $tail = [];
//    while( defined( my $p = pop @$path )) {
    while( $p = array_pop($path) ) {
         if ($debug) { echo "#$indent _reduce_path now path:",  $this->_dump($path), " p:",  $this->_dump($p), "\n"; }

//        if( ref($p) eq 'HASH' ) {
        if( is_array($p) ) {
//            my ($node_head, $node_tail) = _reduce_node($p, _descend($ctx) );
            list ($node_head, $node_tail) = $this->_reduce_node($p, $this->_descend($ctx) );
//            $debug and print "#$indent| head=", _dump($node_head), " tail=", _dump($node_tail), "\n";
            if ($debug) { echo "#$indent| head=", $this->_dump($node_head), " tail=", $this->_dump($node_tail), "\n"; }
//            push @$head, @$node_head if scalar @$node_head;
            if ( count( $node_head) ) {
                 $head = perl_push($head ,$node_head );
            }
//            push @$tail, ref($node_tail) eq 'HASH' ? $node_tail : @$node_tail;
//ここあんまり自信がない.
            $tail = perl_push($tail ,[ $node_tail ] );

//        }
        }
//        else {
        else {
//            if( @$head ) {
            if ( count($head) ) {
//                $debug and print "#$indent| push $p leaves @{[_dump($path)]}\n";
                if ( $debug ) { echo "#$indent| push $p leaves ".$this->_dump($path)."\n"; }
//                push @$tail, $p;
                $tail = perl_push($tail , $p);
//            }
            }
//            else {
            else {
//                $debug and print "#$indent| unshift $p\n";
                if ( $debug ) { echo "#$indent| unshift $p\n"; }
//                unshift @$tail, $p;
                array_unshift($tail, $p);
//            }
            }
//        }
        }
//    }
    }
//    $debug and print "#$indent| tail nr=@{[scalar @$tail]} t0=", ref($tail->[0]),
//        (ref($tail->[0]) eq 'HASH' ? " n=" . scalar(keys %{$tail->[0]}) : '' ),
//        "\n";
    if ( $debug ) { echo  "#$indent| tail nr=".count($tail)." t0="  , gettype($tail[0]) ,
        is_array($tail[0]) ? " n=" . count( $tail[0] ) : '' ,
        "\n"; }
//    if( @$tail > 1
//        and ref($tail->[0]) eq 'HASH'
//        and keys %{$tail->[0]} == 2
//    ) {
    if( count($tail) > 1
        && is_array($tail[0])
        && array_keys($tail[0]) == 2
    ) {
//        my $opt;
        $opt = NULL;
//        my $fixed;
        $fixed = NULL;
//        while( my ($key, $path) = each %{$tail->[0]} ) {
        foreach( $tail[0] as $key => $path ) {
//            $debug and print "#$indent| scan k=$key p=@{[_dump($path)]}\n";
            if ($debug) { echo "#$indent| scan k=$key p=".$this->_dump($path)."\n"; }
//            next unless $path;
            if (! $path ) {
                continue;
            }
//            if (@$path == 1 and ref($path->[0]) eq 'HASH') {
            if (count($path) == 1 && is_array($path[0]) ) {
//                $opt = $path->[0];
                $opt = $path[0];
//            }
            }
//            else {
            else {
//                $fixed = $path;
                $fixed = $path;
//            }
            }
//        }
        }
//        if( exists $tail->[0]{''} ) {
        if( isset($tail[0]['__@UNDEF@__']) ) {
//            my $path = [@{$tail}[1..$#{$tail}]];
            $path = array_slice($tail , 1 );
//            $tail = $tail->[0];
            $tail = $tail[0];
//            ($head, $tail, $path) = _slide_tail( $head, $tail, $path, _descend($ctx) );
            list($head, $tail, $path) = $this->_slide_tail( $head, $tail, $path, $this->_descend($ctx) );
//            $tail = [$tail, @$path];
            $tail = perl_array($tail, $path);
//        }
        }
//    }
    }
//    $debug and print "#$indent _reduce_path $ctx->{depth} out head=", _dump($head), ' tail=', _dump($tail), "\n";
    if ($debug) { echo "#$indent _reduce_path {$ctx['depth']} out head=", $this->_dump($head), ' tail=', $this->_dump($tail), "\n"; }
//    return ($head, $tail);
    return array($head, $tail);
//}
}

//sub _reduce_node {
//    my ($node, $ctx) = @_;
function _reduce_node($node, $ctx) {
//    my $indent = ' ' x $ctx->{depth};
    $indent = str_repeat (' ' , $ctx['depth']);
//    my $debug  =       $ctx->{debug};
    $debug  =       $ctx['debug'];

//    my $optional = _remove_optional($node);
    $optional = $this->_remove_optional($node);
//    $debug and print "#$indent _reduce_node $ctx->{depth} in @{[_dump($node)]} opt=$optional\n";
    if ($debug) { echo "#$indent _reduce_node {$ctx['depth']} in " . $this->_dump($node) ." opt=$optional\n"; }
//    if( $optional and scalar keys %$node == 1 ) {
    if( $optional and count($node) == 1 ) {
//        my $path = (values %$node)[0];
        $path = array_values($node) ;
        $path = $path[0];
//        if( not grep { ref($_) eq 'HASH' } @$path ) {
        if( ! perl_grep(function($_) { return is_array($_); } ,$path ) ) {
//            # if we have removed an optional, and there is only one path
//            # left then there is nothing left to compare. Because of the
//            # optional it cannot participate in any further reductions.
//            # (unless we test for equality among sub-trees).
//            my $result = {
//                ''         => undef,
//                $path->[0] => $path
//            };
            $result = [
                '__@UNDEF@__'         => NULL,
                $path[0] => $path
            ];
//            $debug and print "#$indent| fast fail @{[_dump($result)]}\n";
            if ( $debug ){ echo "#$indent| fast fail ".$this->_dump($result)."\n"; }
//            return [], $result;
            return array([] ,$result);
//        }
        }
//    }
    }

//    my( $fail, $reduce ) = _scan_node( $node, _descend($ctx) );
    list( $fail, $reduce ) = $this->_scan_node( $node, $this->_descend($ctx) );

//    $debug and print "#$indent|_scan_node done opt=$optional reduce=@{[_dump($reduce)]} fail=@{[_dump($fail)]}\n";
    if ($debug) { echo "#$indent|_scan_node done opt=$optional reduce=".$this->_dump($reduce)." fail=".$this->_dump($fail)."\n"; }

//    # We now perform tail reduction on each of the nodes in the reduce
//    # hash. If we have only one key, we know we will have a successful
//    # reduction (since everything that was inserted into the node based
//    # on the value of the last token of each path all mapped to the same
//    # value).

//    if( @$fail == 0 and keys %$reduce == 1 and not $optional) {
    if( count($fail) == 0 and count($reduce) == 1 and ( ! $optional ) ) {
//        # every path shares a common path
//        my $path = (values %$reduce)[0];
        $path = array_values($reduce);
        $path = $path[0];
//        my ($common, $tail) = _do_reduce( $path, _descend($ctx) );
        list ($common, $tail) = $this->_do_reduce( $path, $this->_descend($ctx) );
//        $debug and print "#$indent|_reduce_node  $ctx->{depth} common=@{[_dump($common)]} tail=", _dump($tail), "\n";
        if ( $debug ){ echo "#$indent|_reduce_node  {$ctx['depth']} common=".$this->_dump($common)." tail=", $this->_dump($tail), "\n"; }
//        return( $common, $tail );
        return array( $common, $tail );
//    }
    }

//    # this node resulted in a list of paths, game over
//    $ctx->{indent} = $indent;
    $ctx['indent'] = $indent;
//    return _reduce_fail( $reduce, $fail, $optional, _descend($ctx) );
    return $this->_reduce_fail( $reduce, $fail, $optional, $this->_descend($ctx) );
//}
}

//sub _reduce_fail {
//    my( $reduce, $fail, $optional, $ctx ) = @_;
function _reduce_fail($reduce, $fail, $optional, $ctx) {

//    my( $debug, $depth, $indent ) = @{$ctx}{qw(debug depth indent)};
    $debug  = $ctx['debug'];
    $depth  = $ctx['depth'];
    $indent = $ctx['indent'];
//    my %result;
    $result = [];
//    $result{''} = undef if $optional;
    if ($optional) {
        $result['__@UNDEF@__'] = NULL;
    }

    if ( $debug ) { echo "#$indent| _reduce_fail1 start reduce:" . $this->_dump($reduce)." result:"  . $this->_dump($result) . "\n"; }

//    my $p;
//    for $p (keys %$reduce) {
    foreach(array_keys($reduce) as $p ){
//        my $path = $reduce->{$p};
        $path = $reduce[$p];

        if ( $debug ) { echo "#$indent| _reduce_fail1 path:" . $this->_dump($path)." key: " . $this->_dump($p) . "\n"; }
        
//        if( scalar @$path == 1 ) {
        if( 1 ) { //とりあえずこれで勘弁して。
//            $path = $path->[0];
            $path = $path[0];
//            $debug and print "#$indent| -simple opt=$optional unrev @{[_dump($path)]}\n";
            if ( $debug ) { echo "#$indent| -simple opt=$optional unrev ".$this->_dump($path)."\n"; }
//            $path = _unrev_path($path, _descend($ctx) );
            $path = $this->_unrev_path($path, $this->_descend($ctx) );
//            $debug and print "#$indent| -simple opt=$optional unrev return @{[_dump($path)]}\n";
            if ( $debug ) { echo "#$indent| -simple opt=$optional unrev return".$this->_dump($path)."\n"; }
//            $result{_node_key($path->[0])} = $path;
            $result[ $this->_node_key($path[0]) ] = $path;
//            $debug and print "#$indent| -simple opt=$optional result: @{[_dump($result)]}\n";
            if ( $debug ) { echo "#$indent| -simple opt=$optional result: ".$this->_dump($result)."\n"; }
//        }
        }
//        else {
        else {
//            $debug and print "#$indent| _do_reduce(@{[_dump($path)]})\n";
            if ( $debug ){ echo "#$indent| _do_reduce(".$this->_dump($path)."\n"; }
//            my ($common, $tail) = _do_reduce( $path, _descend($ctx) );
            list ($common, $tail) = $this->_do_reduce( $path, $this->_descend($ctx) );
//            $path = [
//                (
//                    ref($tail) eq 'HASH'
//                        ? _unrev_node($tail, _descend($ctx) )
//                        : _unrev_path($tail, _descend($ctx) )
//                ),
//                @{_unrev_path($common, _descend($ctx) )}
//            ];
            $path = perl_array(
                (
                    is_array($tail)
                        ? $this->_unrev_node($tail, $this->_descend($ctx) )
                        : $this->_unrev_path($tail, $this->_descend($ctx) )
                ),
                $this->_unrev_path($common, $this->_descend($ctx) )
            );
//            $debug and print "#$indent| +reduced @{[_dump($path)]}\n";
            if ( $debug ) { echo  "#$indent| +reduced ".$this->_dump($path)."\n"; }
//            $result{_node_key($path->[0])} = $path;
            $result[ $this->_node_key($path[0]) ] = $path;
//        }
        }
//    }
    }

//    my $f;
//    for $f( @$fail ) {
    foreach( $fail as $f )  {
//        $debug and print "#$indent| +fail @{[_dump($f)]}\n";
        if ( $debug ) { echo "#$indent| +fail ".$this->_dump($f)."\n"; }
//        $result{$f->[0]} = $f;
        $result[$f[0]] = $f;
    }
//    $debug and print "#$indent _reduce_fail $depth fail=@{[_dump(\%result)]}\n";
    if ( $debug ){ echo "#$indent _reduce_fail $depth fail=".$this->_dump($result)."\n"; }
//    return ( [], \%result );
    return array( [] , $result);
//}
}

//sub _scan_node {
//    my( $node, $ctx ) = @_;
function _scan_node( $node, $ctx ) {
//    my $indent = ' ' x $ctx->{depth};
    $indent = str_repeat (' ' , $ctx['depth']);
//    my $debug  =       $ctx->{debug};
    $debug  =       $ctx['debug'];

//    # For all the paths in the node, reverse them. If the first token
//    # of the path is a scalar, push it onto an array in a hash keyed by
//    # the value of the scalar.
//    #
//    # If it is a node, call _reduce_node on this node beforehand. If we
//    # get back a common head, all of the paths in the subnode shared a
//    # common tail. We then store the common part and the remaining node
//    # of paths (which is where the paths diverged from the end and install
//    # this into the same hash. At this point both the common and the tail
//    # are in reverse order, just as simple scalar paths are.
//    #
//    # On the other hand, if there were no common path returned then all
//    # the paths of the sub-node diverge at the end character. In this
//    # case the tail cannot participate in any further reductions and will
//    # appear in forward order.
//    #
//    # certainly the hurgliest function in the whole file :(
//
//    # $debug = 1 if $depth >= 8;
//    my @fail;
    $fail = [];
//    my %reduce;
    $reduce = [];

//    my $n;
//    for $n(
//        map { substr($_, index($_, '#')+1) }
//        sort
//        map {
//            join( '|' =>
//                scalar(grep {ref($_) eq 'HASH'} @{$node->{$_}}),
//                _node_offset($node->{$_}),
//                scalar @{$node->{$_}},
//            )
//            . "#$_"
//        }
//    keys %$node ) {

      $_temp_map = [];
      foreach ( array_keys($node) as $_ ) {

          $_temp_map[] =  join( '|' ,
               [
                    count( perl_grep( function($__){ return is_array($__); } ,$node[$_]  ) ) , 
                    $this->_node_offset($node[$_]),
                    count($node[$_])
               ]
          ) . '#'. $_;   //#は区切り文字 マークする。  いわいる番兵的存在
      }

      $_temp_map2 = [];
      foreach ( perl_sort($_temp_map) as $_ ) {
        $_temp_map2[] = substr($_, strpos($_, '#')+1);  //#の区切り文字を活用
      }

      foreach ($_temp_map2 as $n ) {
//        my( $end, @path ) = reverse @{$node->{$n}};
        $path = array_reverse($node[$n]);
        $end = array_shift($path);

//        if( ref($end) ne 'HASH' ) {
        if ( ! is_array($end) ) {
//            $debug and print "# $indent|_scan_node push reduce ($end:@{[_dump(\@path)]})\n";
            if ($debug) { echo "# $indent|_scan_node push reduce ($end:".$this->_dump($path).")\n"; }
//            push @{$reduce{$end}}, [ $end, @path ];
            if (!isset($reduce[$end])) $reduce[$end] = [];
///////            $reduce[$end] = perl_push( $reduce[$end] , perl_array( $end, $path ) );
            $reduce[$end] = perl_push( $reduce[$end] , [perl_array( $end, $path ) ] ); //配列がひとつネストするらしい？
//        }
        }
//        else {
        else {
//            $debug and print "# $indent|_scan_node head=", _dump(\@path), ' tail=', _dump($end), "\n";
            if ( $debug ) { echo "# $indent|_scan_node head=", $this->_dump($path), ' tail=', $this->_dump($end), "\n"; }
//            my $new_path;
            $new_path = NULL;
//            # deal with sing, singing => s(?:ing)?ing
//            if( keys %$end == 2 and exists $end->{''} ) {
            if( count($end) == 2 and isset( $end['__@UNDEF@__'] ) ) {
//                my ($key, $opt_path) = each %$end;
                list($key, $opt_path) = each($end);
//                ($key, $opt_path) = each %$end if $key eq '';
                if ($key == '') {
                    list($key, $opt_path) = each($end);
                }
//                $opt_path = [reverse @{$opt_path}];
                $opt_path =  array_reverse($opt_path) ;
//                $debug and print "# $indent| check=", _dump($opt_path), "\n";
                if ($debug) { echo "# $indent| check=", _dump($opt_path), "\n"; }
//                my $end = { '' => undef, $opt_path->[0] => [@$opt_path] };
                $end = [ '__@UNDEF@__' => NULL, $opt_path[0] => [ $opt_path ] ];
//                my $head = [];
                $head = [];
//                my $path = [@path];
                $path = [ $path ];
//                ($head, my $slide, $path) = _slide_tail( $head, $end, $path, $ctx );
                list($head, $slide, $path) = $this->_slide_tail( $head, $end, $path, $ctx );
//                if( @$head ) {
                if( count($head) ) {
//                    $new_path = [ @$head, $slide, @$path ];
                    $new_path = perl_array( $head, $slide, $path );
//                }
                }
//            }
            }
//            if( $new_path ) {
            if( $new_path ) {
//                $debug and print "# $indent|_scan_node slid=", _dump($new_path), "\n";
                if ( $debug ) { echo "# $indent|_scan_node slid=", _dump($new_path), "\n"; }
//                push @{$reduce{$new_path->[0]}}, $new_path;
                if (!isset($reduce[$new_path[0]])) $reduce[$new_path[0]] = [];
                $reduce[$new_path[0]] = perl_push( $reduce[$new_path[0]], $new_path );
//            }
            }
//            else {
            else {
//                my( $common, $tail ) = _reduce_node( $end, _descend($ctx) );
                list( $common, $tail ) = $this->_reduce_node( $end, $this->_descend($ctx) );
                if ( $debug ) { echo "# $indent|_scan_node  tail= ", $this->_dump($tail), "\n"; }
//                if( not @$common ) {
                if( ! count($common) ) {
//                       $debug and print "# $indent| +failed $n\n";
                       if ( $debug ) { echo  "# $indent| +failed $n\n"; }
//                       push @fail, [reverse(@path), $tail];
                       if ( $debug ) { echo "# $indent|_scan_node push before fail=", $this->_dump($fail), "\n"; }
///////                       $fail = perl_push( $fail, perl_array( array_reverse($path), $tail ) );   //腑に落ちないが [$tail] とネストするらしい？
                       $fail = perl_push( $fail, perl_array( array_reverse($path), [$tail] ) ); 
                       if ( $debug ) { echo "# $indent|_scan_node push after fail=", $this->_dump($fail), "\n"; }
//                }
                }
//                else {
                else {
//                    my $path = [@path];
                    $path = [ $path ];
//                    $debug and print "# $indent|_scan_node ++recovered common=@{[_dump($common)]} tail=",
//                        _dump($tail), " path=@{[_dump($path)]}\n";
                    if ( $debug ) { echo  "# $indent|_scan_node ++recovered common=".$this->_dump($common)." tail=",
                        $this->_dump($tail), " path=".$this->_dump($path)."\n"; }
//                    if( ref($tail) eq 'HASH'
//                        and keys %$tail == 2
//                    ) {
                    if( is_array( $tail ) 
                        and count($tail) == 2
                    ) {
//                        if( exists $tail->{''} ) {
                        if( isset( $tail['__@UNDEF@__'] ) ){
//                            ($common, $tail, $path) = _slide_tail( $common, $tail, $path, $ctx );
                            list($common, $tail, $path) = $this->_slide_tail( $common, $tail, $path, $ctx );
//                        }
                        }
//                    }
                    }
//                    push @{$reduce{$common->[0]}}, [
//                        @$common, 
//                        (ref($tail) eq 'HASH' ? $tail : @$tail ),
//                        @$path
//                    ];
                    if (!isset($reduce[$common[0]])) $reduce[$common[0]] = [];
                    $reduce[$common[0]] = perl_push( $reduce[$common[0]], 
                        perl_array(
                          $common , 
                          $tail , 
                          $path
                        )
                    );
//                }
                }
//            }
            }
//        }
        }

//    }
    }
//    $debug and print
//        "# $indent|_scan_node counts: reduce=@{[scalar keys %reduce]} fail=@{[scalar @fail]}\n";
    if ( $debug ) { echo 
        "# $indent|_scan_node counts: reduce=". count($reduce) ." fail=" . count($fail) . " dump reduce:" . $this->_dump($reduce)." dump fail:" . $this->_dump($fail)." dump fail2:" . $this->_dump([$fail]). "\n"; }
//    return( \@fail, \%reduce );
/////////    return array( $fail , $reduce );
    if ( count($fail) > 0 )
    {   // よくわからないけど \@されると [] が一つネストするらしい？ なんで？
        //ただし、配列がからの場合は増やしてはいけない。
        $fail = [ $fail ];
    }
    return array( $fail , $reduce );  
//}
}

//sub _do_reduce {
//    my ($path, $ctx) = @_;
function _do_reduce($path, $ctx) {
//    my $indent = ' ' x $ctx->{depth};
    $indent = str_repeat (' ' , $ctx['depth']);
//    my $debug  =       $ctx->{debug};
    $debug  =       $ctx['debug'];
//    my $ra = Regexp::Assemble->new(chomp=>0);
    $ra = new Regexp_Assemble( [ 'chomp' => 0 ] );
//    $ra->debug($debug);
    $ra->debug($debug);
//    $debug and print "# $indent| do @{[_dump($path)]}\n";
    if ($debug) { echo "# $indent| do ".$this->_dump($path)."\n"; }
//    $ra->_insertr( $_ ) for
//        # When nodes come into the picture, we have to be careful
//        # about how we insert the paths into the assembly.
//        # Paths with nodes first, then closest node to front
//        # then shortest path. Merely because if we can control
//        # order in which paths containing nodes get inserted,
//        # then we can make a couple of assumptions that simplify
//        # the code in _insert_node.
//        sort {
//            scalar(grep {ref($_) eq 'HASH'} @$a)
//            <=> scalar(grep {ref($_) eq 'HASH'} @$b)
//                ||
//            _node_offset($b) <=> _node_offset($a)
//                ||
//            scalar @$a <=> scalar @$b
//        }
//        @$path
//    ;
    $_temp_path = perl_sort( function($a,$b){
            $scalar_count_a = 0;
            foreach($a as $_) {
                $scalar_count_a += (is_array($_) ? 1 : 0);
            }
            $scalar_count_b = 0;
            foreach($b as $_) {
                $scalar_count_b += (is_array($_) ? 1 : 0);
            }

            if ($scalar_count_a > $scalar_count_b) {
                 return 1;
            } else if ($scalar_count_a < $scalar_count_b) {
                 return -1;
            }

            $temp_b = $this->_node_offset($b);
            $temp_a = $this->_node_offset($a);
            if ($temp_b > $temp_a) {
                 return 1;
            } else if ($temp_b < $temp_a) {
                 return 1;
            }

            $temp_a = count($a);
            $temp_b = count($a);
            if ($temp_a > $temp_b) {
                 return 1;
            } else if ($temp_a < $temp_b) {
                 return 1;
            }
            return 0;
    } , $path );
    foreach($_temp_path as $_) {
        $ra->_insertr( $_ );
    }

//    $path = $ra->_path;
    $path = $ra->path;
//    my $common = [];
    $common = [];
//    push @$common, shift @$path while( ref($path->[0]) ne 'HASH' );
//これでいいんかな？
    while( count($path) > 0 && !is_array($path[0]) ) {
        $common = perl_push($common, array_shift($path));
    }

//    my $tail = scalar( @$path ) > 1 ? [@$path] : $path->[0];
    $tail = ( count($path) > 1 ) ? $path : $path[0];

//    $debug and print "# $indent| _do_reduce common=@{[_dump($common)]} tail=@{[_dump($tail)]}\n";
   if ($debug){ echo "# $indent| _do_reduce common=@".$this->_dump($common)." tail=".$this->_dump($tail)."\n"; }
//    return ($common, $tail);
   return [ $common, $tail ];
//}
}

//sub _node_offset {
//    # return the offset that the first node is found, or -ve
//    # optimised for speed
//    my $nr = @{$_[0]};
function _node_offset($nr) {
//    my $atom = -1;
//    ref($_[0]->[$atom]) eq 'HASH' and return $atom while ++$atom < $nr;

    //PHPは -1 で最後の要素にアクセスできないため
    //ふつーにやる.
    if (!is_array($nr)){
        return -1;
    }

    //最初に終端を調べるらしい
    $_temp_lastindex = perl_lastindex($nr);
    if ( is_array($nr[$_temp_lastindex]) ) {
        return $_temp_lastindex ;
    }
    //次は頭から検索.
    for( $atom = 0 ; $atom < $_temp_lastindex ; ++$atom ) {
        if ( is_array($nr[$atom]) ) {
            return $atom ;
        }
    }

//    return -1;
    return -1;
//}
}

//sub _slide_tail {
//    my $head   = shift;
//    my $tail   = shift;
//    my $path   = shift;
//    my $ctx    = shift;
function _slide_tail($head,$tail,$path,$ctx) {
//    my $indent = ' ' x $ctx->{depth};
    $indent = str_repeat (' ' , $ctx['depth']);
//    my $debug  =       $ctx->{debug};
    $debug  =       $ctx['debug'];
//    $debug and print "# $indent| slide in h=", _dump($head),
//        ' t=', _dump($tail), ' p=', _dump($path), "\n";
    if ($debug){ echo "# $indent| slide in h=". $this->_dump($head) . ' t='. $this->_dump($tail). ' p='. $this->_dump($path). "\n"; }
//    my $slide_path = (each %$tail)[-1];
//    $slide_path = (each %$tail)[-1] unless defined $slide_path;
    $temp_slide_path = each($tail);
    if ( is_array($temp_slide_path)) {
        $slide_path = $temp_slide_path[-1];
    }
    else {
        $temp_slide_path = each($tail);
        $slide_path = $temp_slide_path[-1];
    }
    
//    $debug and print "# $indent| slide potential ", _dump($slide_path), " over ", _dump($path), "\n";
    if ($debug){ echo "# $indent| slide potential ". $this->_dump($slide_path). " over ". $this->_dump($path), "\n" ; }
//
//    while( defined $path->[0] and $path->[0] eq $slide_path->[0] ) {
    while( $path[0] && $path[0] === $slide_path[0] ) {
//        $debug and print "# $indent| slide=tail=$slide_path->[0]\n";
        if ($debug){ echo "# {$indent}| slide=tail={$slide_path[0]}\n"; }
//        my $slide = shift @$path;
        $slide = $path ;
        array_shift($slide);
//        shift @$slide_path;
        array_shift($slide_path);
//        push @$slide_path, $slide;
        $slide_path = perl_push( $slide_path, $slide);
//        push @$head, $slide;
        $head = perl_push( $head, $slide);
//    }
    }
//    $debug and print "# $indent| slide path ", _dump($slide_path), "\n";
    if ( $debug ){ echo "# {$indent}| slide path ". $this->_dump($slide_path). "\n"; }
//    my $slide_node = {
//        '' => undef,
//        _node_key($slide_path->[0]) => $slide_path,
//    };
    $slide_node = [
           '__@UNDEF@__' => NULL
          ,$this->_node_key($slide_path[0]) => $slide_path
    ];
//    $debug and print "# $indent| slide out h=", _dump($head),
//        ' s=', _dump($slide_node), ' p=', _dump($path), "\n";
    if ($debug) { echo "# $indent| slide out h=". $this->_dump($head) . ' s='. $this->_dump($slide_node). ' p='. $this->_dump($path). "\n"; }
//    return ($head, $slide_node, $path);
    return [$head, $slide_node, $path];
//}
}

//sub _unrev_path {
//    my ($path, $ctx) = @_;
function _unrev_path($path, $ctx) {
//    my $indent = ' ' x $ctx->{depth};
    $indent = str_repeat (' ' , $ctx['depth']);
//    my $debug  =       $ctx->{debug};
    $debug  =       $ctx['debug'];
//    my $new;
    $new = NULL;
//    if( not grep { ref($_) } @$path ) {
    if (count($path) > 0) {
//        $debug and print "# ${indent}_unrev path fast ", _dump($path);
        if ($debug){ echo "# {$indent}_unrev path fast ". $this->_dump($path) ; }
//        $new = [reverse @$path];
        $new = array_reverse($path);
//        $debug and print "#  -> ", _dump($new), "\n";
        if ($debug){ echo "#  -> ". $this->_dump($new). "\n" ; }

//        return $new;
        return $new;
//    }
    }
//    $debug and print "# ${indent}unrev path in ", _dump($path), "\n";
    if ($debug){ echo "# ${indent}unrev path in ". $this->_dump($path). "\n"; }

//    while( defined( my $p = pop @$path )) {
    while( $p = array_pop($path) ) {
//        push @$new,
//              ref($p) eq 'HASH'  ? _unrev_node($p, _descend($ctx) )
//            : ref($p) eq 'ARRAY' ? _unrev_path($p, _descend($ctx) )
//            : $p
//        ;
          $new = perl_push($new ,  
                     (is_array($p) ? $this->_unrev_node($p, $this->_descend($ctx) ) : $p)
          );
//    }
    }
//    $debug and print "# ${indent}unrev path out ", _dump($new), "\n";
    if ( $debug ) { echo "# ${indent}unrev path out ". $this->_dump($new). "\n"; };
//    return $new;
    return $new;
//}
}

//sub _unrev_node {
//    my ($node, $ctx ) = @_;
function _unrev_node($node, $ctx ) {
//    my $indent = ' ' x $ctx->{depth};
    $indent = str_repeat (' ' , $ctx['depth']);
//    my $debug  =       $ctx->debug;
    $debug  =       $ctx['debug'];
//    $optional = _remove_optional($node);
    $optional = $this->_remove_optional($node);
//    $debug and print "# ${indent}unrev node in ", _dump($node), " opt=$optional\n";
    if ( $debug ){ echo "# ${indent}unrev node in ". $this->_dump($node). " opt=$optional\n"; }
//    my $new;
    $new = NULL;
//    $new->{''} = undef if $optional;
    if (!$optional) {
       $new['__@UNDEF@__'] = NULL;
    }
//    my $n;
//    for $n( keys %$node ) {
    foreach( array_keys($node) as $n ){
//        my $path = _unrev_path($node->{$n}, _descend($ctx) );
        $path = $_unrev_path($node->$n, $this->_descend($ctx) );
//        $new->{_node_key($path->[0])} = $path;
        $new[$this->_node_key($path[0])] = $path;
//    }
    }
//    $debug and print "# ${indent}unrev node out ", _dump($new), "\n";
    if ($debug) { echo "# ${indent}unrev node out ", $this->_dump($new), "\n"; }
//    return $new;
    return $new;
//}
}

//sub _node_key {
//    my $node = shift;
function _node_key($node) {
//    return _node_key($node->[0]) if ref($node) eq 'ARRAY';
//    return $node unless ref($node) eq 'HASH';

    if ( !is_array($node) ){
       return $node;
    }
//    my $key = '';
    $key = '';
//    my $k;
//    for $k( keys %$node ) {
    foreach( array_keys($node) as $k ) {
//        next if $k eq '';
        if ($k == '') {
            continue;
        }
//        $key = $k if $key eq '' or $key gt $k;
        if ($key == '' || $key > $k) {
             $key = $k;
        }
//    }
    }
//    return $key;
    return $key;
//}
}

//sub _descend {
//    # Take a context object, and increase the depth by one.
//    # By creating a fresh hash each time, we don't have to
//    # bother adding make-work code to decrease the depth
//    # when we return from what we called.
//    my $ctx = shift;
function _descend($ctx) {
//    return {%$ctx, depth => $ctx->{depth}+1};
    return perl_array( $ctx , ['depth'=> $ctx['depth']+1] );
//}
}

#####################################################################

//sub _make_class {
//    my $self = shift;
function _make_class($args) {
//    my %set = map { ($_,1) } @_;
    $set = [];
    foreach( $args as $_ ){
       $set[$_] = 1;
    }
//    delete $set{'\\d'} if exists $set{'\\w'};
    if ( isset($set['\\w']) ) {
        unset($set{'\\d'});
    }
//    delete $set{'\\D'} if exists $set{'\\W'};
    if ( isset($set['\\W']) ) {
        unset($set{'\\D'});
    }
//    return '.' if exists $set{'.'}
//        or ($self->{fold_meta_pairs} and (
//               (exists $set{'\\d'} and exists $set{'\\D'})
//            or (exists $set{'\\s'} and exists $set{'\\S'})
//            or (exists $set{'\\w'} and exists $set{'\\W'})
//        ))
//    ;
    if (isset($set['.']) 
         || ( $this->fold_meta_pairs && (
                 (isset($set['\\d']) && isset($set['\\D']))
              || (isset($set['\\s']) && isset($set['\\s']))
              || (isset($set['\\w']) && isset($set['\\W']))
         ))
       ){
          return '.';
    }

//    for my $meta( q/\\d/, q/\\D/, q/\\s/, q/\\S/, q/\\w/, q/\\W/ ) {
     foreach( array('\\d', '\\D', '\\s', '\\S', '\\w', '\\W') as $meta ){
//        if( exists $set{$meta} ) {
         if (isset($set[$meta])){
//            my $re = qr/$meta/;
            $re = $meta;
//            my @delete;
            $delete = [];
//            $_ =~ /^$re$/ and push @delete, $_ for keys %set;
            foreach( array_keys($set) as $_) {
                 if ( preg_match("/^{$re}$/u" , $_) ) {
                      $delete = perl_push($delete , $_);
                 }
            }
//            delete @set{@delete} if @delete;
            foreach($delete as $_) {
                unset($set[$_]);
            }
//        }
        }
//    }
    }
//    return (keys %set)[0] if keys %set == 1;
    if ( count($set) == 1 ) {
        return $set[0];
    }
//    for my $meta( '.', '+', '*', '?', '(', ')', '^', '@', '$', '[', '/', ) {
    foreach( array('.', '+', '*', '?', '(', ')', '^', '@', '$', '[', '/') as $meta ){
//        exists $set{"\\$meta"} and $set{$meta} = delete $set{"\\$meta"};
        if (isset($set["\\$meta"]) ){
            $set[$meta] = $set["\\$meta"];
            unset($set["\\$meta"]);
        }
//    }
    }
//    my $dash  = exists $set{'-'} ? do { delete($set{'-'}), '-' } : '';
    $dash = '';
    if ( isset($set['-']) ){
        unset($set['-']);
        $dash = '-';
    }

//    my $caret = exists $set{'^'} ? do { delete($set{'^'}), '^' } : '';
    $caret = '';
    if ( isset($set['^']) ){
        unset($set['^']);
        $caret = '^';
    }

//    my $class = join( '' => sort keys %set );
    $class = join('' , perl_sort(array_keys($set)) );
//    $class =~ s/0123456789/\\d/ and $class eq '\\d' and return $class;
    if ( preg_match('/0123456789/u',$class) ) {
        $class = preg_replace('/0123456789/u' , '\\d' , $class);
        if ($class == '\\d') {
           return $class;
        }
    }
//    return "[$dash$class$caret]";
    return "[$dash$class$caret]";
//}
}

//sub _combine {
//    my $self = shift;
//    my $type = shift;
function _combine($type , $args) {
//    # print "c in = @{[_dump(\@_)]}\n";
//    # my $combine = 
//    return '('
//    . $type
//    . do {
//        my( @short, @long );
//        push @{ /^$Single_Char$/ ? \@short : \@long}, $_ for @_;
//        if( @short == 1 ) {
//            @long = sort _re_sort @long, @short;
//        }
//        elsif( @short > 1 ) {
//            # yucky but true
//            my @combine = (_make_class($self, @short), sort _re_sort @long);
//            @long = @combine;
//        }
//        else {
//            @long = sort _re_sort @long;
//        }
//        join( '|', @long );
//    }
//    . ')';

    $short = [];
    $long = [];
    foreach($args as $_) {
        if (preg_match('/^'.$this->Single_Char.'$/u',$_)) {
            $short[] = $_;
        }
        else {
           $long[] = $_;
        }
    }

    if( count($short) == 1 ) {
        $long = perl_array( perl_sort('_re_sort' , $long )  , $short );
    }
    else if ( count($short) > 1 ) {
        //# yucky but true
        $combine = perl_array( 
               $this->_make_class($short),  perl_sort( '_re_sort' , $long) );
        $long = $combine;
    }
    else {
        $long = perl_sort( '_re_sort' , $long);
    }
    $_temp_do = join( '|', $long );
    
    return '(' 
       . $type
       . $_temp_do
       . ')'
        ;
//    # print "combine <$combine>\n";
//    # $combine;
//}
}

//sub _combine_new {
//    my $self = shift;
function _combine_new($args) {
//    my( @short, @long );
//    push @{ /^$Single_Char$/ ? \@short : \@long}, $_ for @_;
    $short = [];
    $long = [];
    foreach( $args as $_) {
        if (preg_match('/^'.$this->Single_Char.'$/u',$_)) {
            $short[] = $_;
        }
        else {
           $long[] = $_;
        }
    }

//    if( @short == 1 and @long == 0 ) {
    if( count($short) == 1 && count($long) == 0 ) {
//        return $short[0];
        return $short[0];
//    }
    }
//    elsif( @short > 1 and @short == @_ ) {
    else if( count($short) > 1 and (join('|',$short) == join('|',  $args ) ) ) {
//        return _make_class($self, @short);
        return $this->_make_class($short);
//    }
    }
//    else {
    else {
//        return '(?:'
//            . join( '|' =>
//                @short > 1
//                    ? ( _make_class($self, @short), sort _re_sort @long)
//                    : ( (sort _re_sort( @long )), @short )
//            )
//        . ')';
        return '(?:'
            . join( '|' ,
                count($short) > 1
                    ? perl_array( 
                          $this->_make_class($short), perl_sort( '_re_sort' , $long) )
                    : perl_array( 
                          perl_sort( '_re_sort' ,$long) , $short )
            )
        . ')';
//    }
    }
//}
}

//sub _re_path {
//    my $self = shift;
function _re_path($_args) {
//    # in shorter assemblies, _re_path() is the second hottest
//    # routine. after insert(), so make it fast.

//    if ($self->{unroll_plus}) {
    if ($this->unroll_plus) {
//        # but we can't easily make this blockless
//        my @arr = @{$_[0]};
        $arr = $_args[0];
//        my $str = '';
        $str = '';
//        my $skip = 0;
        $skip = 0;
//        for my $i (0..$#arr) {
          for($i = 0 ; $i < perl_lastindex($arr)  ; ++$i) {
//            if (ref($arr[$i]) eq 'ARRAY') {            arrayなのでhashに流す.
//                $str .= _re_path($self, $arr[$i]);
//            }
//            elsif (ref($arr[$i]) eq 'HASH') {
              //arrayで受ける.
              if ( is_array( $arr[$i] ) ) {
//                $str .= exists $arr[$i]->{''}
//                    ? _combine_new( $self,
//                        map { _re_path( $self, $arr[$i]->{$_} ) } grep { $_ ne '' } keys %{$arr[$i]}
//                    ) . '?'
//                    : _combine_new($self, map { _re_path( $self, $arr[$i]->{$_} ) } keys %{$arr[$i]})
//                ;
                if ( isset( $arr[$i]['__@UNDEF@__'] ) ){
                     $_temp_map = [];
                     foreach( perl_grep( function($_){ return $_ != '__@UNDEF@__'; } , array_keys($arr[$i]) ) as $_ ){
                        $_temp_map[] = $this->_re_path( $arr[$i][$_] );  //lamdba captureが長すぎるのでforeachで。
                     }
                     $this->_combine_new($_temp_map). '?';
                }
                else {
                     $_temp_map = [];
                     foreach( array_keys($arr[$i]) as $_ ){
                        $_temp_map[] = $this->_re_path( $arr[$i][$_] );  //lamdba captureが長すぎるのでforeachで。
                     }
                     $this->_combine_new($_temp_map);
                }
//            }
            }
//            elsif ($i < $#arr and $arr[$i+1] =~ /\A$arr[$i]\*(\??)\Z/) {
            else if ($i < perl_lastindex($arr) and preg_match('/\A$arr[$i]\*(\??)\Z/u' , $arr[$i+1] , $pregNum)  ) {
//                $str .= "$arr[$i]+" . (defined $1 ? $1 : '');
                $str .= "$arr[$i]+" . ($pregNum[1] ? $pregNum[1] : '');
//                ++$skip;
                ++$skip;
//            }
            }
//            elsif ($skip) {
            else if ($skip) {
//                $skip = 0;
                $skip = 0;
//            }
            }
//            else {
            else {
//                $str .= $arr[$i];
                $str .= $arr[$i];
//            }
            }
//        }
        }
//        return $str;
        return $str;
//    }
    }

    if (!is_array($_args))
    {
//        foreach(debug_backtrace() as $_) { 
//            echo $_['function'] . ":" . $_['line']."\n";
//        }
//        die;
        return $_args;
    }

//    return join( '', @_ ) unless grep { length ref $_ } @_;
    if ( ! count( perl_grep( function($_){ return is_array($_); } , $_args) )   ) {
        return join( '', $_args );
    }
//    my $p;
//    return join '', map {
//        ref($_) eq '' ? $_
//        : ref($_) eq 'HASH' ? do {
//            # In the case of a node, see whether there's a '' which
//            # indicates that the whole thing is optional and thus
//            # requires a trailing ?
//            # Unroll the two different paths to avoid the needless
//            # grep when it isn't necessary.
//            $p = $_;
//            exists $_->{''}
//            ?  _combine_new( $self,
//                map { _re_path( $self, $p->{$_} ) } grep { $_ ne '' } keys %$_
//            ) . '?'
//            : _combine_new($self, map { _re_path( $self, $p->{$_} ) } keys %$_ )
//        }
//        : _re_path($self, $_) # ref($_) eq 'ARRAY'
//    } @{$_[0]}

    $_temp_join_array = [];
    foreach($_args as $_) {
        if ( is_array($_) ) {
            $p = $_;
            if ( isset($p['__@UNDEF@__']) ) {
                $_temp_map = [];
                foreach( perl_grep( function($__){ return $__ != '__@UNDEF@__'; } , array_keys($p) ) as $___ ){
                    $_temp_map[] = $this->_re_path( $p[$___] ) ;
                }
                $_temp_join_array[] = $this->_combine_new($_temp_map);
            }
            else {
                $_temp_map = [];
                foreach( array_keys($p) as $___ ){
                    $_temp_map[] = $this->_re_path( $p[$___] ) ;
                }
                $_temp_join_array[] = $this->_combine_new($_temp_map);
            }
        }
        else {
            $_temp_join_array[] = $_;
        }
    }
    return join('',$_temp_join_array);
//}
}

//sub _lookahead {
function _lookahead($in) {
//    my $in = shift;
//    my %head;
    $head = [];
//    my $path;
    $path = NULL;
//    for $path( keys %$in ) {
    foreach( array_keys($in) as $path ) {
//        next unless defined $in->{$path};
        if ( ! isset($in[$path]) ) {
            continue;
        }

//        
//        # print "look $path: ", ref($in->{$path}[0]), ".\n";
//        if( ref($in->{$path}[0]) eq 'HASH' ) {
        if ( is_array($in[$path][0]) ) {
//            my $next = 0;
            $next = 0;
//            while( ref($in->{$path}[$next]) eq 'HASH' and @{$in->{$path}} > $next + 1 ) {
            while( is_array($in[$path][$next]) && count($in[$path]) > $next + 1 ) {
//                if( exists $in->{$path}[$next]{''} ) {
                if( isset($in[$path][$next]['__@UNDEF@__']) ) {
//                    ++$head{$in->{$path}[$next+1]};
                    ++$head[$in[$path][$next+1]];
//                }
                }
//                ++$next;
                ++$next;
//            }
            }
//            my $inner = _lookahead( $in->{$path}[0] );
            $inner = $this->_lookahead( $in[$path][0] );
//            @head{ keys %$inner } = (values %$inner);
//perlすごい...            $head{ keys %$inner } = (values %$inner);
            foreach( array_keys($inner) as $_ ){
                 $head[$_] = $_;
            }
//        }
        }
//        elsif( ref($in->{$path}[0]) eq 'ARRAY' ) {
//            my $subpath = $in->{$path}[0]; 
//            for( my $sp = 0; $sp < @$subpath; ++$sp ) {
//                if( ref($subpath->[$sp]) eq 'HASH' ) {
//                    my $follow = _lookahead( $subpath->[$sp] );
//                    @head{ keys %$follow } = (values %$follow);
//                    last unless exists $subpath->[$sp]{''};
//                }
//                else {
//                    ++$head{$subpath->[$sp]};
//                    last;
//                }
//            }
//        }
//arrayなのでskip

//        else {
        else {
//            ++$head{ $in->{$path}[0] };
            $_temp_key = $in[$path][0];
            if ( ! isset( $head[ $_temp_key ] ) ) $head[ $_temp_key ] = 0; //PHP未初期化怒るから...
            ++ $head[ $_temp_key ];
//        }
        }
//    }
    }
//    # print "_lookahead ", _dump($in), '==>', _dump([keys %head]), "\n";
//    return \%head;
    return $head;  //参照ではないけどたぶん大丈夫・・・？
//}
}

//sub _re_path_lookahead {
//    my $self = shift;
//    my $in  = shift;
function _re_path_lookahead($in) {
//    # print "_re_path_la in ", _dump($in), "\n";
//    my $out = '';
    $out = '';
//    for( my $p = 0; $p < @$in; ++$p ) {
    for( $p = 0; $p < count($in) ; ++$p ) {
//        if( ref($in->[$p]) eq '' ) {
        if( !is_array( $in[$p]) ) {
//            $out .= $in->[$p];
            $out .= $in[$p];
//            next;
            continue;
//        }
        }
//        elsif( ref($in->[$p]) eq 'ARRAY' ) {
//            $out .= _re_path_lookahead($self, $in->[$p]);
//            next;
//        }
// arrayなのでスキップ.
//        # print "$p ", _dump($in->[$p]), "\n";
//        my $path = [
//            map { _re_path_lookahead($self, $in->[$p]{$_} ) }
//            grep { $_ ne '' }
//            keys %{$in->[$p]}
//        ];
        $path = [];
        foreach( perl_grep( function($_){ return $_ != '__@UNDEF@__'; } , array_keys($in[$p])) as $_ ) {
             $path[] = $this->_re_path_lookahead( $in[$p][$_] );
        }
//        my $ahead = _lookahead($in->[$p]);
        $ahead = $this->_lookahead($in[$p]);
//        my $more = 0;
        $more = 0;
//        if( exists $in->[$p]{''} and $p + 1 < @$in ) {
        if( isset( $in[$p]['__@UNDEF@__'] ) && $p + 1 < count($in) ) {
//            my $next = 1;
            $next = 1;
//            while( $p + $next < @$in ) {
            while( $p + $next < count($in) ) {
//                if( ref( $in->[$p+$next] ) eq 'HASH' ) {
                if( is_array( $in[$p+$next] ) ) {
//                    my $follow = _lookahead( $in->[$p+$next] );
                    $follow = $this->_lookahead( $in[$p+$next] );
//                    @{$ahead}{ keys %$follow } = (values %$follow);
                    foreach( array_keys($follow) as $_ ){
                         $ahead[$_] = $_;
                    }
//                }
                }
//                else {
                else {
//                    ++$ahead->{$in->[$p+$next]};
                    ++$ahead[$in[$p+$next]];
//                    last;
                    break;
//                }
                }
//                ++$next;
                ++$next;
//            }
            }
//            $more = 1;
            $more = 1;
//        }
        }
//        my $nr_one = grep { /^$Single_Char$/ } @$path;
        $nr_one = perl_grep( function($_){ return preg_match('/^'.$this->Single_Char .'$/u' , $_ ); }  , $path );
//        my $nr     = @$path;
        $nr     = $path;
//        if( $nr_one > 1 and $nr_one == $nr ) {
        if( $nr_one > 1 && $nr_one == $nr ) {
//            $out .= _make_class($self, @$path);
            $out .= $this->_make_class($path);
//            $out .= '?' if exists $in->[$p]{''};
            if ( isset($in[$p]['__@UNDEF@__']) ) {
                 $out .= '?';
            }
//        }
        }
//        else {
        else {
//            my $zwla = keys(%$ahead) > 1
//                ?  _combine($self, '?=', grep { s/\+$//; $_ } keys %$ahead )
//                : '';
            $zwla = count($ahead) > 1
                ?  $this->_combine('?=', perl_grep( function($_) {
                             return preg_replace("/\+$/u" , '' , $_) != '';
                        }
                        , array_keys($ahead)) )
                : '';
//            my $patt = $nr > 1 ? _combine($self, '?:', @$path ) : $path->[0];
            $patt = $nr > 1 ? $this->_combine('?:', $path ) : $path[0];
//            # print "have nr=$nr n1=$nr_one n=", _dump($in->[$p]), ' a=', _dump([keys %$ahead]), " zwla=$zwla patt=$patt @{[_dump($path)]}\n";
//            if( exists $in->[$p]{''} ) {
            if( isset($in[$p]['__@UNDEF@__']) ) {
//                $out .=  $more ? "$zwla(?:$patt)?" : "(?:$zwla$patt)?";
                $out .=  $more ? "$zwla(?:$patt)?" : "(?:$zwla$patt)?";
//            }
            }
//            else {
            else {
//                $out .= "$zwla$patt";
                $out .= "$zwla$patt";
//            }
            }
//        }
        }
//    }
    }
//    return $out;
    return $out;
//}
}

//sub _re_path_track {
//    my $self      = shift;
//    my $in        = shift;
//    my $normal    = shift;
//    my $augmented = shift;
function _re_path_track($in,$normal,$augmented) {
//    my $o;
    $o = NULL;
//    my $simple  = '';
    $simple  = '';
//    my $augment = '';
    $augment = '';
//    for( my $n = 0; $n < @$in; ++$n ) {
    for( $n = 0; $n < count($in) ; ++$n ) {
//        if( ref($in->[$n]) eq '' ) {
        if( ! is_array( $in[$n]) ) {
//            $o = $in->[$n];
            $o = $in[$n];
//            $simple  .= $o;
            $simple  .= $o;
//            $augment .= $o;
            $augment .= $o;
//            if( (
//                    $n < @$in - 1
//                    and ref($in->[$n+1]) eq 'HASH' and exists $in->[$n+1]{''}
//                )
//                or $n == @$in - 1
//            ) {
            if( (
                    $n < count($in) - 1
                    && is_array( $in[$n+1] ) and isset( $in[$n+1]['__@UNDEF@__'] )
                )
                || $n == count($in) - 1
            ) {
//                push @{$self->{mlist}}, $normal . $simple ;
                $this->mlist = perl_push( $this->mlist, $normal . $simple );
//                $augment .= $] < 5.009005
//                    ? "(?{\$self->{m}=$self->{mcount}})"
//                    : "(?{$self->{mcount}})"
//                ;
                $augment .="(?{$this->mcount})";
//                ++$self->{mcount};
                ++$this->mcount;
//            }
            }
//        }
        }
//        else {
        else {
//            my $path = [
//                map { $self->_re_path_track( $in->[$n]{$_}, $normal.$simple , $augmented.$augment ) }
//                grep { $_ ne '' }
//                keys %{$in->[$n]}
//            ];
            $path = [];
            foreach(  perl_grep( function($_){ return $_ != '__@UNDEF@__'; } , 
                                                array_keys($in[$n]) ) as $_ )     {
                 $path[] = $this->_re_path_track( $in[$n][$_], $normal.$simple , $augmented.$augment );
            }
//            $o = '(?:' . join( '|' => sort _re_sort @$path ) . ')';
            $o = '(?:' . join( '|' , perl_sort( '_re_sort' , $path) ) . ')';
//            $o .= '?' if exists $in->[$n]{''};
            if ( isset( $in[$n]['__@UNDEF@__'] ) ) {
                 $o .= '?';
            }
//            $simple  .= $o;
            $simple  .= $o;
//            $augment .= $o;
            $augment .= $o;
//        }
        }
//    }
    }
//    return $augment;
    return $augment;
//}
}

//sub _re_path_pretty {
//    my $self = shift;
//    my $in  = shift;
//    my $arg = shift;
function _re_path_pretty($in,$arg) {
//    my $pre    = ' ' x (($arg->{depth}+0) * $arg->{indent});
    $pre    = str_repeat(' ' , (($arg['depth']+0) * $arg['indent']) );
//    my $indent = ' ' x (($arg->{depth}+1) * $arg->{indent});
    $indent = str_repeat(' ' , (($arg['depth']+1) * $arg['indent']) );
//    my $out = '';
    $out = '';
//    $arg->{depth}++;
    $arg['depth']++;
//    my $prev_was_paren = 0;
    $prev_was_paren = 0;
//    for( my $p = 0; $p < @$in; ++$p ) {
    foreach( $in as $p ) {
//        if( ref($in->[$p]) eq '' ) {
        if ($p == '' ) {
//            $out .= "\n$pre" if $prev_was_paren;
            if ($prev_was_paren) {
                 $out .= "\n$pre";
            }
//            $out .= $in->[$p];
            $out .= $p;
//            $prev_was_paren = 0;
            $prev_was_paren = 0;
//        }
        }
//        elsif( ref($in->[$p]) eq 'ARRAY' ) {
        else if( is_array($p) ) {
//            $out .= _re_path($self, $in->[$p]);
            $out .= $this->_re_path( $p );
//        }
        }
//        else {
        else {
//            my $path = [
//                map { _re_path_pretty($self, $in->[$p]{$_}, $arg ) }
//                grep { $_ ne '' }
//                keys %{$in->[$p]}
//            ];
            $path = [];
            foreach( array_keys($p) as $pp) {
                 if ($pp != ''){
                     $path[] = $this->_re_path_pretty($p, $arg );
                 }
            }

//            my $nr = @$path;
//            my( @short, @long );
//            push @{/^$Single_Char$/ ? \@short : \@long}, $_ for @$path;
            $short = [];
            $long = [];
            foreach($path as $_) {
                if (preg_match('/^'.$this->Single_Char.'$/u',$_)) {
                    $short[] = $_;
                }
                else {
                   $long[] = $_;
                }
            }
//            if( @short == $nr ) {
            if ( join('|',$path) == join('|',$short) ) {
//                $out .=  $nr == 1 ? $path->[0] : _make_class($self, @short);
                $out .=  $nr == 1 ? $path[0] : $this->_make_class($short);
//                $out .= '?' if exists $in->[$p]{''};
                if ($p) {
                     $out .= '?';
                }
//            }
            }
//            else {
            else {
//                $out .= "\n" if length $out;
                if ( strlen($out) ) {
                    $out .= "\n";
                }
//                $out .= $pre if $p;
                if ( $p ) {
                    $out .= $pre;
                }
//                $out .= "(?:\n$indent";
                $out .= "(?:\n$indent";
//                if( @short < 2 ) {
                if( count($short) < 2 ) {
//                    my $r = 0;
                    $r = 0;
//                    $out .= join( "\n$indent|" => map {
//                            $r++ and $_ =~ s/^\(\?:/\n$indent(?:/;
//                            $_
//                        }
//                        sort _re_sort @$path
//                    );
                    $_temp_map_array = [];
                    foreach( perl_sort( '_re_sort' , $path ) as $_ ) {
                            if ($r++) {
                                $_ = preg_replace("/^\(\?:/u" ,"/\n$indent(?:/" , $_ );
                            }
                            $_temp_map_array[] = $_;
                    }

                    $out .= join("\n$indent|" , $_temp_map_array );
//                }
                }
//                else {
                else {
//                    $out .= join( "\n$indent|" => ( (sort _re_sort @long), _make_class($self, @short) ));
                    $out .= join( "\n$indent|" , perl_array( perl_sort( '_re_sort' , $long) , $this->_make_class($short) ) );
//                }
                }
//                $out .= "\n$pre)";
                $out .= "\n$pre)";
//                if( exists $in->[$p]{''} ) {
                if( isset($in[$p]['__@UNDEF@__']) ) {
//                    $out .= "\n$pre?";
                    $out .= "\n$pre?";
//                    $prev_was_paren = 0;
                    $prev_was_paren = 0;
//                }
                }
//                else {
                else {
//                    $prev_was_paren = 1;
                    $prev_was_paren = 1;
//                }
                }
//            }
            }
//        }
        }
//    }
    }
//    $arg->{depth}--;
    $arg['depth']--;
//    return $out;
    return $out;
//}
}

//sub _node_eq {
function _node_eq($p1 , $p2 = NULL) {
//    return 0 if not defined $_[0] or not defined $_[1];
    if ($p2  === NULL ) {
        return 0;
    }
//    return 0 if ref $_[0] ne ref $_[1];
    if (gettype($p1) !== gettype($p1) ) {
        return 0;
    }
//    # Now that we have determined that the reference of each
//    # argument are the same, we only have to test the first
//    # one, which gives us a nice micro-optimisation.
//    if( ref($_[0]) eq 'HASH' ) {
    if( is_array($p1) ) {
//        keys %{$_[0]} == keys %{$_[1]}
//            and
//        # does this short-circuit to avoid _re_path() cost more than it saves?
//        join( '|' => sort keys %{$_[0]}) eq join( '|' => sort keys %{$_[1]})
//            and
//        _re_path(undef, [$_[0]] ) eq _re_path(undef, [$_[1]] );
        if ( count($p1) == count($p2) ) {
            if (
                join('',perl_sort(array_keys($p1)) ) 
                 === 
                join('',perl_sort(array_keys($p2)) 
            )) {
                if ( $this->_re_path( NULL , $p1 ) && $this->_re_path( NULL , $p2 ) ) {
                      return true;
                }
            }
        }
        return false;
//    }
    }
//    elsif( ref($_[0]) eq 'ARRAY' ) {                          //SKIP
//        scalar @{$_[0]} == scalar @{$_[1]}                    //SKIP
//            and                                               //SKIP
//        _re_path(undef, $_[0]) eq _re_path(undef, $_[1]);     //SKIP
//    }                                                         //SKIP
//    else {
    else {
//        $_[0] eq $_[1];
        return $p1 && $p2;
//    }
    }
//}
}

//sub _pretty_dump {
function _pretty_dump($p) {
//    return sprintf "\\x%02x", ord(shift);
    return sprintf( "\\x%02x", ord($p));
//}
}

//sub _dump {
//    my $path = shift;
//    return _dump_node($path) if ref($path) eq 'HASH';
//    my $dump = '[';
//    my $d;
//    my $nr = 0;
//    for $d( @$path ) {
//    foreach($path as $d) {
//        $dump .= ' ' if $nr++;
//        if( ref($d) eq 'HASH' ) {
//            $dump .= _dump_node($d);
//        }
//        elsif( ref($d) eq 'ARRAY' ) {   //skip
//            $dump .= _dump($d);         //skip
//        }                               //skip
//        elsif( defined $d ) {
//            # D::C indicates the second test is redundant
//            # $dump .= ( $d =~ /\s/ or not length $d )
//            $dump .= (
//                $d =~ /\s/            ? qq{'$d'}         :
//                $d =~ /^[\x00-\x1f]$/ ? _pretty_dump($d) :
//                $d
//            );
//        }
//        else {
//            $dump .= '*';
//        }
//    }
//    return $dump . ']';
//}

//sub _dump_node {
//    my $node = shift;
//    my $dump = '{';
//    my $nr   = 0;
//    my $n;
//    for $n (sort keys %$node) {
//        $dump .= ' ' if $nr++;
//        # Devel::Cover shows this to test to be redundant
//        # $dump .= ( $n eq '' and not defined $node->{$n} )
//        $dump .= $n eq ''
//            ? '*'
//            : ($n =~ /^[\x00-\x1f]$/ ? _pretty_dump($n) : $n)
//                . "=>" . _dump($node->{$n})
//        ;
//    }
//    return $dump . '}';
//}

//perl だとハッシュの概念があるので、phpにそのままだと移植できない。
//適当にごまかしながら移植してみる。
function _dump($path) {
    if ( !is_array($path) ) {
        if ( preg_match('/\s/u' , $path) ) {
            return "'{$path}'";
        }
        else if ( preg_match('/^[\x00-\x1f]$/u' , $path) ) {
            return $this->_pretty_dump($path);
        }
        else {
           return $path;
        }
    }

    //きれいに見せるために順番でソートする.
    $keys = perl_sort(array_keys($path));

    //調査
    $count = 0;
    foreach($keys as $n) {
        if ($n !== $count) {
           break;
        }
//        echo "$n VS $count   ";
        $count ++;
    }
//var_dump($count);
    if ($count == count($keys) ) {
       //たぶん配列
         $nr   = 0;
         $dump = '[';
         foreach($keys as $n) {
             if ($nr++) {
                $dump .= ' ';
             }
             $dump .= $this->_dump($path[$n]);
         }
         return $dump . ']';
    }
    else {
       //たぶんハッシュ
         $nr   = 0;
         $dump = '{';
         foreach($keys as $n) {
             if ($nr++) {
                $dump .= ' ';
             }
             
             if ($n === '__@UNDEF@__') {
                $dump .= '*';
             }
             else {
                if ( preg_match('/^[\x00-\x1f]$/u',$n) ) {
                     $dump .= $this->_pretty_dump($n);
                }
                else
                {
                     $dump .= $n;
                }

                $dump .= "=>" . $this->_dump( $path[$n] );
             }
         }
         return $dump . '}';
    }
}

/*
=back

=head1 DIAGNOSTICS

  "Cannot pass a C<refname> to Default_Lexer"

You tried to replace the default lexer pattern with an object
instead of a scalar. Solution: You probably tried to call
C<< $obj->Default_Lexer >>. Call the qualified class method instead
C<Regexp::Assemble::Default_Lexer>.

  "filter method not passed a coderef"

  "pre_filter method not passed a coderef"

A reference to a subroutine (anonymous or otherwise) was expected.
Solution: read the documentation for the C<filter> method.

  "duplicate pattern added: /.../"

The C<dup_warn> attribute is active, and a duplicate pattern was
added (well duh!). Solution: clean your data.

  "cannot open [file] for input: [reason]"

The C<add_file> method was unable to open the specified file for
whatever reason. Solution: make sure the file exists and the script
has the required privileges to read it.

=head1 NOTES

This module has been tested successfully with a range of versions
of perl, from 5.005_03 to 5.9.3. Use of 5.6.0 is not recommended.

The expressions produced by this module can be used with the PCRE
library.

Remember to "double up" your backslashes if the patterns are
hard-coded as constants in your program. That is, you should
literally C<add('a\\d+b')> rather than C<add('a\d+b')>. It
usually will work either way, but it's good practice to do so.

Where possible, supply the simplest tokens possible. Don't add
C<X(?-\d+){2})Y> when C<X-\d+-\d+Y> will do. The reason is that
if you also add C<X\d+Z> the resulting assembly changes
dramatically: C<X(?:(?:-\d+){2}Y|-\d+Z)> I<versus>
C<X-\d+(?:-\d+Y|Z)>. Since R::A doesn't perform enough analysis,
it won't "unroll" the C<{2}> quantifier, and will fail to notice
the divergence after the first C<-d\d+>.

Furthermore, when the string 'X-123000P' is matched against the
first assembly, the regexp engine will have to backtrack over each
alternation (the one that ends in Y B<and> the one that ends in Z)
before determining that there is no match. No such backtracking
occurs in the second pattern: as soon as the engine encounters the
'P' in the target string, neither of the alternations at that point
(C<-\d+Y> or C<Z>) could succeed and so the match fails.

C<Regexp::Assemble> does, however, know how to build character
classes. Given C<a-b>, C<axb> and C<a\db>, it will assemble these
into C<a[-\dx]b>. When C<-> (dash) appears as a candidate for a
character class it will be the first character in the class. When
C<^> (circumflex) appears as a candidate for a character class it
will be the last character in the class.

It also knows about meta-characters than can "absorb" regular
characters. For instance, given C<X\d> and C<X5>, it knows that
C<5> can be represented by C<\d> and so the assembly is just C<X\d>.
The "absorbent" meta-characters it deals with are C<.>, C<\d>, C<\s>
and C<\W> and their complements. It will replace C<\d>/C<\D>,
C<\s>/C<\S> and C<\w>/C<\W> by C<.> (dot), and it will drop C<\d>
if C<\w> is also present (as will C<\D> in the presence of C<\W>).

C<Regexp::Assemble> deals correctly with C<quotemeta>'s propensity
to backslash many characters that have no need to be. Backslashes on
non-metacharacters will be removed. Similarly, in character classes,
a number of characters lose their magic and so no longer need to be
backslashed within a character class. Two common examples are C<.>
(dot) and C<$>. Such characters will lose their backslash.

At the same time, it will also process C<\Q...\E> sequences. When
such a sequence is encountered, the inner section is extracted and
C<quotemeta> is applied to the section. The resulting quoted text
is then used in place of the original unquoted text, and the C<\Q>
and C<\E> metacharacters are thrown away. Similar processing occurs
with the C<\U...\E> and C<\L...\E> sequences. This may have surprising
effects when using a dispatch table. In this case, you will need
to know exactly what the module makes of your input. Use the C<lexstr>
method to find out what's going on:

  $pattern = join( '', @{$re->lexstr($pattern)} );

If all the digits 0..9 appear in a character class, C<Regexp::Assemble>
will replace them by C<\d>. I'd do it for letters as well, but
thinking about accented characters and other glyphs hurts my head.

In an alternation, the longest paths are chosen first (for example,
C<horse|bird|dog>). When two paths have the same length, the path
with the most subpaths will appear first. This aims to put the
"busiest" paths to the front of the alternation. For example, the
list C<bad>, C<bit>, C<few>, C<fig> and C<fun> will produce the
pattern C<(?:f(?:ew|ig|un)|b(?:ad|it))>. See F<eg/tld> for a
real-world example of how alternations are sorted. Once you have
looked at that, everything should be crystal clear.

When tracking is in use, no reduction is performed. nor are 
character classes formed. The reason is that it is
too difficult to determine the original pattern afterwards. Consider the
two patterns C<pale> and C<palm>. These should be reduced to
C<pal[em]>. The final character matches one of two possibilities.
To resolve whether it matched an C<'e'> or C<'m'> would require
keeping track of the fact that the pattern finished up in a character
class, which would the require a whole lot more work to figure out
which character of the class matched. Without character classes
it becomes much easier. Instead, C<pal(?:e|m)> is produced, which
lets us find out more simply where we ended up.

Similarly, C<dogfood> and C<seafood> should form C<(?:dog|sea)food>.
When the pattern is being assembled, the tracking decision needs
to be made at the end of the grouping, but the tail of the pattern
has not yet been visited. Deferring things to make this work correctly
is a vast hassle. In this case, the pattern becomes merely
C<(?:dogfood|seafood>. Tracked patterns will therefore be bulkier than
simple patterns.

There is an open bug on this issue:

L<http://rt.perl.org/rt3/Ticket/Display.html?id=32840>

If this bug is ever resolved, tracking would become much easier to
deal with (none of the C<match> hassle would be required - you could
just match like a regular RE and it would Just Work).

=head1 SEE ALSO

=over 8

=item L<perlre>

General information about Perl's regular expressions.

=item L<re>

Specific information about C<use re 'eval'>.

=item Regex::PreSuf

C<Regex::PreSuf> takes a string and chops it itself into tokens of
length 1. Since it can't deal with tokens of more than one character,
it can't deal with meta-characters and thus no regular expressions.
Which is the main reason why I wrote this module.

=item Regexp::Optimizer

C<Regexp::Optimizer> produces regular expressions that are similar to
those produced by R::A with reductions switched off. It's biggest
drawback is that it is exponentially slower than Regexp::Assemble on
very large sets of patterns.

=item Regexp::Parser

Fine grained analysis of regular expressions.

=item Regexp::Trie

Funnily enough, this was my working name for C<Regexp::Assemble>
during its developement. I changed the name because I thought it
was too obscure. Anyway, C<Regexp::Trie> does much the same as
C<Regexp::Optimizer> and C<Regexp::Assemble> except that it runs
much faster (according to the author). It does not recognise
meta characters (that is, 'a+b' is interpreted as 'a\+b').

=item Text::Trie

C<Text::Trie> is well worth investigating. Tries can outperform very
bushy (read: many alternations) patterns.

=item Tree::Trie

C<Tree::Trie> is another module that builds tries. The algorithm that
C<Regexp::Assemble> uses appears to be quite similar to the
algorithm described therein, except that C<R::A> solves its
end-marker problem without having to rewrite the leaves.

=back

=head1 LIMITATIONS

C<Regexp::Assemble> does not attempt to find common substrings. For
instance, it will not collapse C</cabababc/> down to C</c(?:ab){3}c/>.
If there's a module out there that performs this sort of string
analysis I'd like to know about it. But keep in mind that the
algorithms that do this are very expensive: quadratic or worse.

C<Regexp::Assemble> does not interpret meta-character modifiers.
For instance, if the following two patterns are
given: C<X\d> and C<X\d+>, it will not determine that C<\d> can be
matched by C<\d+>. Instead, it will produce C<X(?:\d|\d+)>. Along
a similar line of reasoning, it will not determine that C<Z> and
C<Z\d+> is equivalent to C<Z\d*> (It will produce C<Z(?:\d+)?>
instead).

You cannot remove a pattern that has been added to an object. You'll
just have to start over again. Adding a pattern is difficult enough,
I'd need a solid argument to convince me to add a C<remove> method.
If you need to do this you should read the documentation for the
C<clone> method.

C<Regexp::Assemble> does not (yet)? employ the C<(?E<gt>...)>
construct.

The module does not produce POSIX-style regular expressions. This
would be quite easy to add, if there was a demand for it.

=head1 BUGS

Patterns that generate look-ahead assertions sometimes produce
incorrect patterns in certain obscure corner cases. If you
suspect that this is occurring in your pattern, disable
lookaheads.

Tracking doesn't really work at all with 5.6.0. It works better
in subsequent 5.6 releases. For maximum reliability, the use of
a 5.8 release is strongly recommended. Tracking barely works with
5.005_04. Of note, using C<\d>-style meta-characters invariably
causes panics. Tracking really comes into its own in Perl 5.10.

If you feed C<Regexp::Assemble> patterns with nested parentheses,
there is a chance that the resulting pattern will be uncompilable
due to mismatched parentheses (not enough closing parentheses). This
is normal, so long as the default lexer pattern is used. If you want
to find out which pattern among a list of 3000 patterns are to blame
(speaking from experience here), the F<eg/debugging> script offers
a strategy for pinpointing the pattern at fault. While you may not
be able to use the script directly, the general approach is easy to
implement.

The algorithm used to assemble the regular expressions makes extensive
use of mutually-recursive functions (that is, A calls B, B calls
A, ...) For deeply similar expressions, it may be possible to provoke
"Deep recursion" warnings.

The module has been tested extensively, and has an extensive test
suite (that achieves close to 100% statement coverage), but you
never know...  A bug may manifest itself in two ways: creating a
pattern that cannot be compiled, such as C<a\(bc)>, or a pattern
that compiles correctly but that either matches things it shouldn't,
or doesn't match things it should. It is assumed that Such problems
will occur when the reduction algorithm encounters some sort of
edge case. A temporary work-around is to disable reductions:

  my $pattern = $assembler->reduce(0)->re;

A discussion about implementation details and where bugs might lurk
appears in the README file. If this file is not available locally,
you should be able to find a copy on the Web at your nearest CPAN
mirror.

Seriously, though, a number of people have been using this module to
create expressions anywhere from 140Kb to 600Kb in size, and it seems to
be working according to spec. Thus, I don't think there are any serious
bugs remaining.

If you are feeling brave, extensive debugging traces are available to
figure out where assembly goes wrong.

Please report all bugs at
L<http://rt.cpan.org/NoAuth/Bugs.html?Dist=Regexp-Assemble>

Make sure you include the output from the following two commands:

  perl -MRegexp::Assemble -le 'print $Regexp::Assemble::VERSION'
  perl -V

There is a mailing list for the discussion of C<Regexp::Assemble>.
Subscription details are available at
L<http://listes.mongueurs.net/mailman/listinfo/regexp-assemble>.

=head1 ACKNOWLEDGEMENTS

This module grew out of work I did building access maps for Postfix,
a modern SMTP mail transfer agent. See L<http://www.postfix.org/>
for more information. I used Perl to build large regular expressions
for blocking dynamic/residential IP addresses to cut down on spam
and viruses. Once I had the code running for this, it was easy to
start adding stuff to block really blatant spam subject lines, bogus
HELO strings, spammer mailer-ids and more...

I presented the work at the French Perl Workshop in 2004, and the
thing most people asked was whether the underlying mechanism for
assembling the REs was available as a module. At that time it was
nothing more that a twisty maze of scripts, all different. The
interest shown indicated that a module was called for. I'd like to
thank the people who showed interest. Hey, it's going to make I<my>
messy scripts smaller, in any case.

Thomas Drugeon was a valuable sounding board for trying out
early ideas. Jean Forget and Philippe Blayo looked over an early
version. H.Merijn Brandt stopped over in Paris one evening, and
discussed things over a few beers.

Nicholas Clark pointed out that while what this module does
(?:c|sh)ould be done in perl's core, as per the 2004 TODO, he
encouraged me to continue with the development of this module. In
any event, this module allows one to gauge the difficulty of
undertaking the endeavour in C. I'd rather gouge my eyes out with
a blunt pencil.

Paul Johnson settled the question as to whether this module should
live in the Regex:: namespace, or Regexp:: namespace. If you're
not convinced, try running the following one-liner:

  perl -le 'print ref qr//'

Philippe Bruhat found a couple of corner cases where this module
could produce incorrect results. Such feedback is invaluable,
and only improves the module's quality.

=head1 AUTHOR

David Landgren

Copyright (C) 2004-2011. All rights reserved.

  http://www.landgren.net/perl/

If you use this module, I'd love to hear about what you're using
it for. If you want to be informed of updates, send me a note.

You can look at the latest working copy in the following
Subversion repository:

  http://svnweb.mongueurs.net/Regexp-Assemble

=head1 LICENSE

This library is free software; you can redistribute it and/or modify
it under the same terms as Perl itself.

=cut

'The Lusty Decadent Delights of Imperial Pompeii';
__END__
*/
}



$a = new Regexp_Assemble();
$a->debug(255);
echo $a->_dump( ['A' => [ 'X','Y','Z'],'1' => [ '4','5','6'] ] );

//$a->add("123");
$a->add("ABC");
//$a->add("678");
$a->add("ABC");
$a->add("ADE");
$a->add("ABN");
//$a->add("こいよベネット");
//$a->add("こいよアグネス");

//$a->add( 'ab+c' );
//$a->add( 'ab+-' );
//$a->add( 'a\\w\\d+' );
//$a->add( 'a\\d+' );
//  print $ra->re; # prints a(?:\w?\d+|b+[-c])

$str = $a->re();
var_dump($str);