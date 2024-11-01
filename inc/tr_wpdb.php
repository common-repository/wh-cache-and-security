<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Tr_wpdb extends wpdb
{
    public function __construct( $dbuser, $dbpassword, $dbname, $dbhost ) {
        register_shutdown_function( array( $this, '__destruct' ) );

        if ( WP_DEBUG && WP_DEBUG_DISPLAY )
            $this->show_errors();

        /* Use ext/mysqli if it exists and:
         *  - WP_USE_EXT_MYSQL is defined as false, or
         *  - We are a development version of WordPress, or
         *  - We are running PHP 5.5 or greater, or
         *  - ext/mysql is not loaded.
         */
        if ( function_exists( 'mysqli_connect' ) ) {
            if ( defined( 'WP_USE_EXT_MYSQL' ) ) {
                $this->use_mysqli = ! WP_USE_EXT_MYSQL;
            } elseif ( version_compare( phpversion(), '5.5', '>=' ) || ! function_exists( 'mysql_connect' ) ) {
                $this->use_mysqli = true;
            } elseif ( false !== strpos( $GLOBALS['wp_version'], '-' ) ) {
                $this->use_mysqli = true;
            }
        }

        $this->dbuser = $dbuser;
        $this->dbpassword = $dbpassword;
        $this->dbname = $dbname;
        $this->dbhost = $dbhost;

        // wp-config.php creation will manually connect when ready.
        if ( defined( 'WP_SETUP_CONFIG' ) ) {
            return;
        }

    }

    public function query( $query ) {
        if ( ! $this->ready ) {
            if(empty($this->dbh))
            {
                if (  $this->db_connect() ) {

                }else{
                    $this->check_current_query = true;
                    return false;
                }
            }else{
                $this->check_current_query = true;
                return false;
            }

        }

        /**
         * Filter the database query.
         *
         * Some queries are made before the plugins have been loaded,
         * and thus cannot be filtered with this method.
         *
         * @since 2.1.0
         *
         * @param string $query Database query.
         */
        $query = apply_filters( 'query', $query );

        $this->flush();

        // Log how the function was called
        $this->func_call = "\$db->query(\"$query\")";

        // If we're writing to the database, make sure the query will write safely.
        if ( $this->check_current_query && ! $this->check_ascii( $query ) ) {
            $stripped_query = $this->strip_invalid_text_from_query( $query );
            // strip_invalid_text_from_query() can perform queries, so we need
            // to flush again, just to make sure everything is clear.
            $this->flush();
            if ( $stripped_query !== $query ) {
                $this->insert_id = 0;
                return false;
            }
        }

        $this->check_current_query = true;

        // Keep track of the last query for debug..
        $this->last_query = $query;

        $this->_do_query( $query );

        // MySQL server has gone away, try to reconnect
        $mysql_errno = 0;
        if ( ! empty( $this->dbh ) ) {
            if ( $this->use_mysqli ) {
                $mysql_errno = mysqli_errno( $this->dbh );
            } else {
                $mysql_errno = mysql_errno( $this->dbh );
            }
        }

        if ( empty( $this->dbh ) || 2006 == $mysql_errno ) {
            if ( $this->check_connection() ) {
                $this->_do_query( $query );
            } else {
                $this->insert_id = 0;
                return false;
            }
        }

        // If there is an error then take note of it..
        if ( $this->use_mysqli ) {
            $this->last_error = mysqli_error( $this->dbh );
        } else {
            $this->last_error = mysql_error( $this->dbh );
        }

        if ( $this->last_error ) {
            // Clear insert_id on a subsequent failed insert.
            if ( $this->insert_id && preg_match( '/^\s*(insert|replace)\s/i', $query ) )
                $this->insert_id = 0;

            $this->print_error();
            return false;
        }

        if ( preg_match( '/^\s*(create|alter|truncate|drop)\s/i', $query ) ) {
            $return_val = $this->result;
        } elseif ( preg_match( '/^\s*(insert|delete|update|replace)\s/i', $query ) ) {
            if ( $this->use_mysqli ) {
                $this->rows_affected = mysqli_affected_rows( $this->dbh );
            } else {
                $this->rows_affected = mysql_affected_rows( $this->dbh );
            }
            // Take note of the insert_id
            if ( preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
                if ( $this->use_mysqli ) {
                    $this->insert_id = mysqli_insert_id( $this->dbh );
                } else {
                    $this->insert_id = mysql_insert_id( $this->dbh );
                }
            }
            // Return number of rows affected
            $return_val = $this->rows_affected;
        } else {
            $num_rows = 0;
            if ( $this->use_mysqli && $this->result instanceof mysqli_result ) {
                while ( $row = @mysqli_fetch_object( $this->result ) ) {
                    $this->last_result[$num_rows] = $row;
                    $num_rows++;
                }
            } elseif ( is_resource( $this->result ) ) {
                while ( $row = @mysql_fetch_object( $this->result ) ) {
                    $this->last_result[$num_rows] = $row;
                    $num_rows++;
                }
            }

            // Log number of rows the query returned
            // and return number of rows selected
            $this->num_rows = $num_rows;
            $return_val     = $num_rows;
        }

        return $return_val;
    }

    private function _do_query( $query ) {
        if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
            $this->timer_start();
        }

        if ( $this->use_mysqli ) {
            $this->result = @mysqli_query( $this->dbh, $query );
        } else {
            $this->result = @mysql_query( $query, $this->dbh );
        }
        $this->num_queries++;

        if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
            $this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );
        }
    }

    public function db_version() {
        if(empty($this->dbh)){
            $this->db_connect();
        }
        if ( $this->use_mysqli ) {
            $server_info = mysqli_get_server_info( $this->dbh );
        } else {
            $server_info = mysql_get_server_info( $this->dbh );
        }
        return preg_replace( '/[^0-9.].*/', '', $server_info );
    }
}