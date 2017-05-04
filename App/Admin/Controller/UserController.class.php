<?php
/**
 *
 * 作    者：han
 * 日    期：2016-01-20
 * 版    本：1.0.0
 * 功能说明：用户控制器。
 *
 **/

namespace  Admin\Controller;

class UserController extends ComController
{
    //用户列表
    public function index()
    {

        $p = isset($_GET['p']) ? intval($_GET['p']) : '1';
        $field = isset($_POST['field']) ? $_POST['field'] : '';
        $keyword = isset($_POST['keyword']) ? htmlentities($_POST['keyword']) : '';
        $order = isset($_POST['order']) ? $_POST['order'] : 'DESC';
        $where = '';

        $prefix = C('DB_PREFIX');

        if ($keyword <> '') {
            if ($field == 'user') {
                $where = "{$prefix}user.user LIKE '%$keyword%'";
            }
            if ($field == 'phone') {
                $where = "{$prefix}user.phone LIKE '%$keyword%'";
            }
            if ($field == 'qq') {
                $where = "{$prefix}user.qq LIKE '%$keyword%'";
            }
            if ($field == 'email') {
                $where = "{$prefix}user.email LIKE '%$keyword%'";
            }
        }


        $user = M('user');
        $pagesize = 10;
        $offset = $pagesize * ($p - 1);
        $count = $user->field("{$prefix}user.*")
            //->order($order)
            ->join("LEFT JOIN {$prefix}member ON {$prefix}user.user = {$prefix}member.user")
            ->where($where)
            ->count();

        $list = $user->field("{$prefix}user.*,{$prefix}member.uid as mid")
            //->order($order)
            ->join("LEFT JOIN {$prefix}member ON {$prefix}user.user = {$prefix}member.user")
            ->where($where)
            ->limit($offset . ',' . $pagesize)
            ->select();
        //$user->getLastSql();
        $page = new \Think\Page($count, $pagesize);
        $page = $page->show();
        $this->assign('list', $list);
        $this->assign('page', $page);
        $group = M('auth_group')->field('id,title')->select();
        $this->assign('group', $group);
        $this->display();
    }

    //开通主播
    public function add()
    {
        $uid = isset($_GET['uid']) ? intval($_GET['uid']) : '';
        $rs = M('user')->where("uid=$uid")->find();
        $user = M("Member")->where(array('user' => $rs['user']))->find();
        if($user){
            $this->error('此用户已经是主播了');
        }

        $MemberData['user'] = $rs['user'];
        $MemberData['password'] = $rs['password'];
        $MemberData['t'] = time();
        $MemberData['sex'] =  '';
        $MemberData['head'] =  '';
        $MemberData['birthday'] =  '';
        $MemberData['phone'] = '';
        $MemberData['qq'] = '';
        $MemberData['email'] =  '';
        $MemberData['head'] =  '/Public/attached/201601/1453389194.png';
        $MemberData['gold'] =  0;
        $uid = M('member')->data($MemberData)->add();

        $Authdata['uid'] = $uid;
        $Authdata['group_id'] = 8;
        $uid = M('auth_group_access')->data($Authdata)->add();
        $this->success('操作成功！');
        
    }

    //兑换虚拟货币
    public function FictitiousRmb(){
        $fictitious_rmb = C('FICTITIOUS_RMB_RANGE');

        $list = array(99,999,9999,99999);//兑换列表

        foreach($list as $k=>$v){
            $data[$k]['get_fictitious_rmb'] = floor($v*$fictitious_rmb);
            $data[$k]['fictitious_rmb'] = $v;
        }

        $user = member(intval($this->USER['uid']));
        $total_fictitious_rmb = $user['gold']*$fictitious_rmb;
        $this->assign('total_fictitious_rmb', $total_fictitious_rmb);
        $this->assign('list', $data);
        $this->display();
    }


    //提现
    public function rmb(){

        /*$star = date("Y-m-01",time());
        $end = date("Y-m-31",time());
        $map['t'] = array('between',"$star,$end");
        $rs = M('trade_log')->where($map)->select();
        if($rs){
            $this->error('每个月只能提现一次');
        }

        $day = date("d",time());
        if($day<1 || $day>5){
            $this->error('每个月1-5号为提现日');
        }*/

        $list = array(99,999,9999,99999);//兑换列表

        $rmb = C('RMB_RANGE');

        foreach($list as $k=>$v){
            $data[$k]['rmb'] = floor($v*$rmb);
            $data[$k]['fictitious_rmb'] = $v;
        }

        $user = member(intval($this->USER['uid']));
        $total_rmb = floor($user['gold']*$rmb);
        $this->assign('total_rmb', $total_rmb);
        $this->assign('list', $data);
        $this->display();
    }
    
    public function OutCash(){
        $fictitious_rmb = C('FICTITIOUS_RMB_RANGE');
        $rmb_range = C('RMB_RANGE');
        $num = intval($_POST['num']);
        $type = $_POST['type'];
        $uid = intval($this->USER['uid']);
        $tran_result = true;
        $user = member($uid);

        $data['uid'] = $uid;
        $data['name'] = $user['user'];
        $data['t'] = date('Y-m-d',time());
        $data['num'] = $num;
        $data['order_sn'] = ordersn();

        if($num<=0){
            $this->error('兑换数量不能小于0');
        }

        if($type==1){//兑换虚拟货币
            $need_fictitious_gold = ceil($num/$fictitious_rmb);
            if($user['gold']<$need_fictitious_gold){
                $this->error('兑换数量超过实际拥有数量');
            }
            $data['type'] = 1;//虚拟货币兑换
            $data['fictitious_rmb'] = $num;
            $data['out_gold'] = $need_fictitious_gold;
            $gold = $user['gold']-$need_fictitious_gold;
            $trans = M();
            $trans->startTrans();
            try {
                M('member')->data(array('gold' => $gold))->where("uid=$uid")->save();
                M('trade_log')->data($data)->add();
            }catch (Exception $ex) {
                $tran_result = false;
            }
            if ($tran_result === false) {
                $trans->rollback();
                $this->error('兑换失败！');
            } else {
                $trans->commit();
                $this->success('兑换成功！');
            }

        }else{//提现
            $need_gold = ceil($num/$rmb_range);
            if($user['gold']<$need_gold){
                $this->error('兑换数量超过实际拥有数量');
            }

            $data['type'] = 2;//虚拟货币兑换
            $data['rmb'] = $num;
            $data['out_gold'] = $need_gold;
            $gold = $user['gold']-$need_gold;
            $trans = M();
            $trans->startTrans();
            try {
                M('member')->data(array('gold' => $gold))->where("uid=$uid")->save();
                M('trade_log')->data($data)->add();
            }catch (Exception $ex) {
                $tran_result = false;
            }
            if ($tran_result === false) {
                $trans->rollback();
                $this->error('提现失败！');
            } else {
                $trans->commit();
                $this->success('提现成功！');
            }

        }
        exit();
    }

    public function ExchangeInfo(){
        $fictitious_rmb = C('FICTITIOUS_RMB_RANGE');
        $rmb_range = C('RMB_RANGE');
        $p = isset($_GET['p']) ? intval($_GET['p']) : '1';
        $end = isset($_POST['end']) ? $_POST['end'] : '';
        $star = isset($_POST['star']) ? $_POST['star'] : '';
        $uid = intval($this->USER['uid']);

        $user = member($uid);
        $GetMoney['fictitious_rmb'] = floor($user['gold']*$fictitious_rmb);
        $GetMoney['rmb'] = floor($user['gold']*$rmb_range);

        $map['uid']  = $uid;

        if ($end <> '' && $star<>'') {
            $map['t']  = array('between',"$star,$end");
        }


        $trade = M('trade_log');
        $pagesize = 10;
        $offset = $pagesize * ($p - 1);
        $count = $trade->where($map)
            ->count();

        $list = $trade->where($map)
            ->limit($offset . ',' . $pagesize)
            ->select();
        //$user->getLastSql();
        $page = new \Think\Page($count, $pagesize);
        $page = $page->show();

        $this->assign('moneylist', $GetMoney);
        $this->assign('list', $list);
        $this->assign('page', $page);

        $this->display();
    }

    public function AdminExchangeInfo(){
        $fictitious_rmb = C('FICTITIOUS_RMB_RANGE');
        $rmb_range = C('RMB_RANGE');
        $p = isset($_GET['p']) ? intval($_GET['p']) : '1';
        $end = isset($_POST['end']) ? $_POST['end'] : '';
        $star = isset($_POST['star']) ? $_POST['star'] : '';
        $uid = intval($this->USER['uid']);

        $user = member($uid);
        $GetMoney['fictitious_rmb'] = floor($user['gold']*$fictitious_rmb);
        $GetMoney['rmb'] = floor($user['gold']*$rmb_range);

        //$map['uid']  = $uid;

        if ($end <> '' && $star<>'') {
            $map['t']  = array('between',"$star,$end");
        }


        $trade = M('trade_log');
        $pagesize = 10;
        $offset = $pagesize * ($p - 1);
        $count = $trade->where($map)
            ->count();

        $list = $trade->where($map)
            ->limit($offset . ',' . $pagesize)
            ->select();
        //$user->getLastSql();
        $page = new \Think\Page($count, $pagesize);
        $page = $page->show();

        $this->assign('moneylist', $GetMoney);
        $this->assign('list', $list);
        $this->assign('page', $page);

        $this->display();
    }

    public function HowTime(){
        $p = isset($_GET['p']) ? intval($_GET['p']) : '1';
        $end = isset($_POST['end']) ? $_POST['end'] : '';
        $star = isset($_POST['star']) ? $_POST['star'] : '';
        $uid = intval($this->USER['uid']);

        $map['uid'] = $uid;
        if ($end <> '' && $star<>'') {
            $map['t']  = array('between',"$star,$end");
        }


        $trade = M('show_live');
        $pagesize = 10;
        $offset = $pagesize * ($p - 1);
        $count = $trade->where($map)
            ->count();

        $list = $trade->where($map)
            ->limit($offset . ',' . $pagesize)
            ->select();
        //$user->getLastSql();
        $page = new \Think\Page($count, $pagesize);
        $page = $page->show();


        $this->assign('list', $list);
        $this->assign('page', $page);
        $this->display();
    }
}
