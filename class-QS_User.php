<?php
/**
 * QS_User
 *  This Class is responsible for handling users in Wordpress.
 *  With this Class you can Create - Edit - Get - Delete.
 *
 * @category    Wordpress
 * @author      NivNoiman
 * Text-Domain: qs_user
 */
class QS_User {
    /* ###### Properties ###### */
    public $userData; // returned user data
    protected static $wp_user; // wordpress user object
    protected static $itemType;
    protected static $error; // error array for messages.
    protected static $success; // success array for messages.
    protected static $passwordFlag = false; // password flag for randomize password

    /* ###### Magic ###### */
    /**
     * __construct
     * If declared class with values - the first action will be the creation of a new user.
     * @param [array] $args [ args to create new user ]
     */
    function __construct( $args = NULL ){
        if( !empty( $args ) && is_array( $args ) )
            return $this->create( $args );
        elseif( is_user_logged_in() )
            return $this->get( get_current_user_id() );
    }
    /* ###### Functions ###### */
    /**
     * get
     * When calling this method with Unique User Data [ like ID ]
     * you recive all user information.
     * @param  [int/string] $id     [ ID / Email ]
     * @param  [string]     $output [ The type of information you want to receive ]
     * @return User Information - dependent the output property
     */
    public function get( $id , $output = NULL ){
        if( !is_wp_error( self::$itemType = self::checkUserItem( $id ) ) )
            return $this->userData = self::outputUser( $output );
    }

    /**
     * create
     * Create New user
     * @param  [array] $args [ args to create new user ( email , user_name , password .... ) ]
     * @param  string  $return [ The type of information you want to receive ( array / ID ) ]
     * @return User Information - dependent the return property
     */
    public function create( $args , $return = 'ID' ){
        $args = self::filterArgs( $args );
        if( !self::isUserExist( $args ) ){
            if( $args['ID'] = wp_create_user( $args['user_name'], $args['password'], $args['user_email'] ) ){
                self::checkAndInsertMeta( $args );
                self::$success['create'] = __( 'New User Created.', 'qs_user' );
                if( self::$passwordFlag )
                    self::sendEmail( $args );
                if( $return == 'array' )
                    return $this->get( $args['ID'] );
                else
                    return $args['ID'];
            }
        }
    }

    /**
     * edit
     * Edit existing user.
     * @param  [int/string] $id     [ ID / Email ]
     * @param  [array] $args [ args to edit user ( email , user_name , password .... ) ]
     * @param  string  $return [ The type of information you want to receive ( array / ID ) ]
     * @return [bool] true if success , false if error
     */
    public function edit( $id , $args , $return = 'ID' ){
        $existing = $this->get( $id );
        $args = self::filterArgs( $args , false );
        if( self::isUserExist( $existing ) ){
            self::removeMsg( 'error' , 'exist' );
            self::checkAndInsertMeta( array_merge( array( 'ID' => $existing['ID'] ) , $args ) );
            if( empty( self::$error ) ){
                self::$success['update'] = __( 'User updated.', 'qs_user' );
                return true;
            } else
                return false;
        }
    }
    /**
     * delete
     * Delete existing user and reassing post and links to new user.
     * @param  [int/string] $id     [ ID / Email ]
     * @param  [int/string] $reassign [ Reassign posts and links to new User ( ID / Email ). ]
     * @return [bool] true if success , false if error
     */
    public function delete( $id , $reassign = NULL ){
        if( !function_exists('wp_delete_user') && defined( 'ABSPATH' ) )
            include( ABSPATH . "wp-admin/includes/user.php" );
        $reassignToUser = NULL;
        $userToDelete   = $this->get( $id )['ID'];
        if( !empty( $reassign ) )
            $reassignToUser = $this->get( $reassign )['ID'];
        if( wp_delete_user( $userToDelete , $reassignToUser ) ){
            self::$success['deleted'] = __( 'User deleted.', 'qs_user' );
            return true;
        }else{
            self::$error['deleted'] = __( "User cant be deleted", "qs_user" );
            return false;
        }
    }

    /**
     * login
     * Create login attemption - if all args true - signon
     * @param  [array] $args [ args to edit user ( user_login , user_password , remember .... ) ]
     * @return [bool] true if success , false if error
     */
    public function login( $args ){
        $args = self::filterArgs( $args , false );
        if( !empty( $args['user_login'] ) && !empty( $args['user_password'] ) ){
            if ( is_email( $args['user_login'] ) ){
                $args['user_login'] = $this->get( $args['user_login'] );
                if( !empty( $args['user_login'] ) && !is_wp_error( $args['user_login'] ) )
                    $args['user_login'] = $args['user_login']['user_name'];
            }
            $user = wp_signon( $args, false );
            if( !empty( $user ) && !is_wp_error( $user ) ){
                self::$success['loggedin'] = __( 'Success - Please Wait....', 'qs_user' );
                return true;
            } else{
                self::$error['exist'] = __( 'This user not exist', 'qs_user' );
                return false;
            }
        } else{
            self::$error['empty'] = __( "Empty Fields", "qs_user" );
            return false;
        }
    }

    /**
     * error
     * Retrive all your errors from your last action
     * @return [array]
     */
    public function error(){
        if( !empty( self::$error ) )
            return self::$error;
    }
    /**
     * success
     * Retrive all your success from your last action
     * @return [array]
     */
    public function success(){
        if( !empty( self::$success ) )
            return self::$success;
    }
    /**
     * removeMsg
     * Delete exsisting messages from error and success array.
     * @param  [string] $type [ success / error ]
     * @param  string  $key [ The key in the array ( type of message ) ]
     */
    protected static function removeMsg( $type , $key ){
        if( $type == 'error' || $type == 'success' )
            unset( self::${$type}[ $key ] );
    }

    /**
     * checkUserItem
     * Check if the user unique data is allow.
     * @param  [int/string] $item [ ID / Email ]
     * @return [array] the type of the item and his value
     */
    protected static function checkUserItem( $item ){
        $return = array();
        if( is_numeric( $item ) )
            $return['type'] = 'ID';
        else if( is_email( $item ) )
            $return['type'] = 'email';
        else{
            self::$error['get_method'] = __( "Illegal Item", "qs_user" );
            return new WP_Error( 'error', __( "Illegal item", "qs_user" ) );
        }

        $return['value'] = $item;

        return $return;
    }

    /**
     * outputUser
     * Dependent your type - this functon return the user information
     * @param  [string/array] $type [ what type of information - ( array - meta ) ]
     * @return User Information
     */
    protected static function outputUser( $type ){
        if( is_array( $type ) ){
            $metaKey = $type;
            $type    = 'meta';
        }
        self::$wp_user  = get_user_by( self::$itemType['type'] , self::$itemType['value'] );
        if( empty( self::$wp_user ) ){
            self::$error['exist'] = __( 'This user not exist', 'qs_user' );
            return;
        }
        switch ( $type ) {
            case 'wordpress':
            case 'wp':
                return self::$wp_user;
            case 'meta':
                if( empty( $metaKey ) )
                    return get_user_meta( self::$wp_user->ID);
                else{
                    foreach( $metaKey as $key => $metaType ){
                        if( $metaType == 'repeater' )
                            $return[ $key ] = self::getRepeaterMetaData( $key );
                        else
                            $return[ $key ] = get_user_meta( self::$wp_user->ID , $key );
                    }
                    return $return;
                }
            default:
                return self::wrapUser( $type );
        }
    }

    /**
     * wrapUser
     * The wrapper information for the class output ( default - array )
     * @param  string   $type [ type of output ( for this version just "array" ) ]
     * @return User Information - dependent the output property
     */
    protected static function wrapUser( $type = 'array' ){
        $wpUser         = self::$wp_user;
        $wrap_constract = array(
            'ID'         => 'ID',
            'user_name'  => 'user_login',
            'user_email' => 'user_email',
            'user_url'   => 'data->user_url',
            'name'       => 'display_name',
            'role'       => array( 'roles' , 0)
        );

        foreach( $wrap_constract as $type => $path ){
            if( is_array( $path ) ){
                $outputUserData[ $type ] = $wpUser->{$path[0]};
                $outputUserData[ $type ] = $outputUserData[ $type ][ $path[1] ];
            } else
                $outputUserData[ $type ] = $wpUser->{$path};
        }
        return $outputUserData;
    }

    /**
     * filterArgs
     * Clean and organize the args for user - before database's querys.
     * Use this after you've got all your args for user ( edit / create )
     * @param  [array] $args [ args to create new user ( email , user_name , password .... ) ]
     * @param  [bool]  $password [ true - if password is missing, generate new password  ]
     * @return [array] the args after the filtering ( ready to use )
     */
    protected static function filterArgs( $args , $password = true ){
        foreach( $args as $argKey => $argValue ){
            if( $argKey == 'email' || $argKey == 'user_email' ){
                if( is_email( $argValue = sanitize_email( $argValue ) ) )
                    $args[ 'user_email' ] = $argValue;
                else
                    self::$error['email'] = __( "Illegal email", "qs_user" );
            }
            elseif( $argKey != 'password')
                $args[ $argKey ] = sanitize_text_field( $argValue );
        }
        if( empty( $args['password'] ) && $password ){
            $args['password'] = wp_generate_password( $length = 12, $include_standard_special_chars = false );
            self::$passwordFlag = true;
        }
        if( empty( self::$error ) )
            return $args;
        else
            return self::$error;
    }

    /**
     * isUserExist
     * Check if user exist
     * @param  [ array ] $args [ user data args - ( for check existing you'll need -user_email- or -user_name- ) ]
     * @return boolean true/false ( exist / not exist )
     */
    protected static function isUserExist( $args ){
        if( !empty( $args['user_email'] ) || !empty( $args['user_name'] ) || !empty( $args['user_login'] ) ){
            if(( !username_exists( $args['user_name'] ) || !username_exists( $args['user_login'] ) ) and email_exists( $args['user_email'] ) == false )
                return false;
            else {
                self::$error['exist'] = __( "This user already exist.", "qs_user" );
                return true;
            }
        }
    }

    /**
     * checkAndInsertMeta
     * Check user args and insert to database ( after user exist )
     * @param  [array] $args [ args to edit user ( email , user_name , password .... ) - For this function don't forget to include the user ID]
     */
    protected static function checkAndInsertMeta( $args ){
        $exclude_keys = array(
            'user_name',
            'password',
            'user_email',
            'role',
            'ID',
            'first_name',
            'last_name',
            'user_nicename',
            'display_name',
            'description',
            'show_admin_bar_front'
        );
        if( !empty( $args['ID'] ) ){
            foreach( $args as $argKey => $argValue ){
                if( !in_array( $argKey , $exclude_keys ) ){
                    if( !update_user_meta( $args['ID'], $argKey, $argValue ) )
                        self::$error['meta'][ $argKey ] = sprintf( __( 'The meta %1$s = %2$s not saved in the database.', 'qs_user' ), $argKey , $argValue );
                } else{
                    if( is_wp_error( wp_update_user( array( 'ID' => $args['ID'], $argKey => $argValue ) ) ) )
                        self::$error['data'][ $argKey ] = sprintf( __( 'The data %1$s = %2$s not saved in the database.', 'qs_user' ), $argKey , $argValue );
                }
            }
        }
    }

    protected static function getRepeaterMetaData( $key ){
        global $wpdb;
        $userID = self::$wp_user->ID;
        $sql = "SELECT um.meta_key , um.meta_value
                FROM $wpdb->usermeta um
                WHERE 1 = 1
                AND um.user_id = $userID
                AND um.meta_key LIKE '{$key}_%'
                ";
        return $results = $wpdb->get_results( $sql );
    }

    /**
     * sendEmail
     * Send user registering information via email.
     * @param  [array] $args [ user information data ]
     */
    protected static function sendEmail( $args ){
        $to = $args['user_email'];
        $subject  = sprintf( __( '%s - Registration Info', 'qs_user' ), get_bloginfo('name') );
        $message  = __( 'Hi ,<br /></br />Thank you for registering. Your account has been set up and you can log in using the following details -<br /><br />', 'qs_user' );
        $message .= sprintf( __( '<strong>Username:</strong> %s', 'qs_user' ), $args['user_name'] );
        $message .= sprintf( __( '<strong>Password:</strong> %s', 'qs_user' ), $args['password'] );
        $headers  = 'MIME-Version: 1.0'."\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1'."\r\n";
        $headers .= 'From: '. get_bloginfo('name') .' <no-reply@'. str_replace( 'http://www.' , get_site_url() ) .'>'."\r\n";

        wp_mail( $to, $subject, $message, $headers );
    }
}
?>
