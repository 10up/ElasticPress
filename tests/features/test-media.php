<?php

class EPTestMediaFeature extends EP_Test_Base {
	
	/**
	 * Setup each test.
	 */
	public function setUp() {
		
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();
		
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		
		wp_set_current_user( $admin_id );
		
		ep_delete_index();
		ep_put_mapping();
		
		EP_WP_Query_Integration::factory()->setup();
		EP_Sync_Manager::factory()->setup();
		EP_Sync_Manager::factory()->sync_post_queue = array();
		
		$this->setup_test_post_type();
	}
	
	/**
	 * Clean up after each test. Reset our mocks
	 */
	public function tearDown() {
		parent::tearDown();
		
		//make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}
	
	/**
	 * Test create and index attachment
	 */
	public function testIndexAttachment() {
		if( EP_Features::factory()->registered_features['media']->is_active() ) {
			global $wpdb;
			
			$id = ep_media_upload_pdf();
			
			$args = array(
				'ep_integrate' => true,
				'post__in' => array( $id ),
				'post_type' => 'attachment',
				'post_status' => 'inherit',
			);
			
			$query = new WP_Query( $args );
			
			$this->assertEquals( 1, sizeof( $query->posts ) );
			$this->assertEquals( $id, $query->posts[0]->ID );
			$this->assertTrue( "SELECT * FROM {$wpdb->posts} WHERE 1=0" == $query->request );
		}
	}
	
	/**
	 * Test attachment content
	 */
	public function testSearchAttachmentContent() {
		if( EP_Features::factory()->registered_features['media']->is_active() ) {
			global $wpdb;
			
			$id = ep_media_upload_pdf();
			
			$args = array(
				's' => 'SearchThisText',
				'post_type' => 'attachment',
				'post_status' => 'inherit',
			);
			
			$query = new WP_Query( $args );
			
			$this->assertEquals( 1, sizeof( $query->posts ) );
			$this->assertEquals( $id, $query->posts[0]->ID );
			$this->assertTrue( "SELECT * FROM {$wpdb->posts} WHERE 1=0" == $query->request );
		}
	}
	
	/**
	 * Test search posts by attachment content
	 */
	public function testSearchPostByAttachmentContent() {
		if( EP_Features::factory()->registered_features['media']->is_active() ) {
			global $wpdb;
			
			$id = ep_create_and_sync_post();
			
			ep_media_upload_pdf( array( 'post_parent' => $id ) );
			
			$args = array(
				's' => 'SearchThisText',
			);
			
			$query = new WP_Query( $args );
			
			$this->assertEquals( 1, sizeof( $query->posts ) );
			$this->assertEquals( $id, $query->posts[0]->ID );
			$this->assertTrue( "SELECT * FROM {$wpdb->posts} WHERE 1=0" == $query->request );
		}
	}
	
}